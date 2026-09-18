<?php

use App\Models\Genre;
use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(GenreSeeder::class));

function genreSlugsOf(Radio $radio): array
{
    return $radio->genres()->pluck('slug')->sort()->values()->all();
}

it('rewrites the pivot and the genre/artist label from the stored tags column', function () {
    $radio = Radio::factory()->create([
        'genre' => 'Stale Label',
        'artist' => 'Stale Label',
        'tags' => 'rock,pop',
    ]);
    $radio->genres()->sync([Genre::where('slug', 'other')->value('id')]);

    $this->artisan('radios:remap-genres')->assertSuccessful();

    $radio->refresh();

    // rock (specificity 2) beats pop (specificity 1), so it wins primary().
    expect($radio->genre)->toBe('Rock')
        ->and($radio->artist)->toBe('Rock')
        ->and(genreSlugsOf($radio))->toBe(['pop', 'rock']);
});

it('makes no network calls at all', function () {
    Http::fake();

    Radio::factory()->create(['tags' => 'jazz']);

    $this->artisan('radios:remap-genres')->assertSuccessful();

    Http::assertNothingSent();
});

it('writes nothing in --dry-run mode', function () {
    $otherId = Genre::where('slug', 'other')->value('id');

    $radio = Radio::factory()->create([
        'genre' => 'Stale Label',
        'artist' => 'Stale Label',
        'tags' => 'rock,pop',
    ]);
    $radio->genres()->sync([$otherId]);

    $this->artisan('radios:remap-genres', ['--dry-run' => true])->assertSuccessful();

    $radio->refresh();

    expect($radio->genre)->toBe('Stale Label')
        ->and($radio->artist)->toBe('Stale Label')
        ->and(genreSlugsOf($radio))->toBe(['other']);
});

it('leaves stations with NULL tags completely untouched', function () {
    $otherId = Genre::where('slug', 'other')->value('id');

    $radio = Radio::factory()->create([
        'genre' => 'Custom Label',
        'artist' => 'Custom Label',
        'tags' => null,
    ]);
    $radio->genres()->sync([$otherId]);

    $this->artisan('radios:remap-genres')->assertSuccessful();

    $radio->refresh();

    expect($radio->genre)->toBe('Custom Label')
        ->and($radio->artist)->toBe('Custom Label')
        ->and(genreSlugsOf($radio))->toBe(['other']);
});

it('leaves the seeded favourites with no tags untouched beyond their genre mapping', function () {
    $this->seed(RadioSeeder::class);

    $before = Radio::where('source', 'seed')->get(['id', 'title', 'artist', 'genre', 'url', 'image']);

    $this->artisan('radios:remap-genres')->assertSuccessful();

    $after = Radio::where('source', 'seed')->get(['id', 'title', 'artist', 'genre', 'url', 'image']);

    expect($after->toArray())->toBe($before->toArray());
});

it('is safe to run repeatedly', function () {
    Radio::factory()->create(['tags' => 'rock,pop,jazz']);
    Radio::factory()->create(['tags' => 'deep house,house']);
    Radio::factory()->create(['tags' => null]);

    $this->artisan('radios:remap-genres')->assertSuccessful();
    $firstPass = Radio::with('genres')->get()->map(fn ($r) => [$r->genre, genreSlugsOf($r)])->all();
    $pivotCountAfterFirstRun = DB::table('genre_radio')->count();

    $this->artisan('radios:remap-genres')->assertSuccessful();
    $secondPass = Radio::with('genres')->get()->map(fn ($r) => [$r->genre, genreSlugsOf($r)])->all();

    expect($secondPass)->toBe($firstPass)
        ->and(DB::table('genre_radio')->count())->toBe($pivotCountAfterFirstRun);
});

it('processes every station regardless of chunk size', function () {
    // The untouched row's genre is pinned to a value the taxonomy never
    // produces, so it can't coincidentally collide with one of the labels
    // the other four rows are remapped to below.
    Radio::factory()->count(5)->sequence(
        ['tags' => 'rock'],
        ['tags' => 'pop'],
        ['tags' => 'jazz'],
        ['tags' => null, 'genre' => 'Untouched', 'artist' => 'Untouched'],
        ['tags' => 'deep house'],
    )->create();

    $this->artisan('radios:remap-genres', ['--chunk' => 1])->assertSuccessful();

    expect(Radio::where('genre', 'Rock')->count())->toBe(1)
        ->and(Radio::where('genre', 'Pop')->count())->toBe(1)
        ->and(Radio::where('genre', 'Jazz')->count())->toBe(1)
        ->and(Radio::where('genre', 'Deep House')->count())->toBe(1)
        ->and(Radio::where('genre', 'Untouched')->count())->toBe(1);
});

it('maps a station tagged with deep house onto both deep-house and house genres', function () {
    $radio = Radio::factory()->create(['tags' => 'deep house']);

    $this->artisan('radios:remap-genres')->assertSuccessful();

    $radio->refresh();

    expect($radio->genre)->toBe('Deep House')
        ->and(genreSlugsOf($radio))->toBe(['deep-house', 'house']);
});
