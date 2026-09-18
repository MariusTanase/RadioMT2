<?php

use App\Models\Genre;
use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;

it('seeds every genre defined in config', function () {
    $this->seed(GenreSeeder::class);

    expect(Genre::count())->toBe(count(config('radio-genres.genres')));
});

it('always includes the other genre as a fallback bucket', function () {
    $this->seed(GenreSeeder::class);

    expect(Genre::where('slug', 'other')->exists())->toBeTrue();
});

it('can be seeded twice without duplicating genres', function () {
    $this->seed(GenreSeeder::class);
    $before = Genre::count();

    $this->seed(GenreSeeder::class);

    expect(Genre::count())->toBe($before);
});

it('marks the twenty seeded stations as favourites from the seed source', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::where('source', 'seed')->count())->toBe(20)
        ->and(Radio::where('is_favourite', true)->count())->toBe(20)
        ->and(Radio::whereNotNull('station_uuid')->count())->toBe(0);
});

it('relates radios to genres both ways', function () {
    $this->seed(GenreSeeder::class);
    $this->seed(RadioSeeder::class);

    $radio = Radio::first();
    $pop = Genre::where('slug', 'pop')->firstOrFail();

    $radio->genres()->attach($pop);

    expect($radio->fresh()->genres)->toHaveCount(1)
        ->and($pop->radios()->count())->toBe(1);
});
