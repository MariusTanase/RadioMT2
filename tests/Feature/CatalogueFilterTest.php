<?php

use App\Models\Genre;
use App\Models\Radio;
use App\Support\CatalogueFilter;
use Database\Seeders\GenreSeeder;
use Illuminate\Http\Request;

beforeEach(fn () => $this->seed(GenreSeeder::class));

function filterFor(array $query): CatalogueFilter
{
    return new CatalogueFilter(Request::create('/', 'GET', $query));
}

it('defaults to no search, no genre, played sort, and page one', function () {
    $filter = filterFor([]);

    expect($filter->q)->toBeNull()
        ->and($filter->genre)->toBeNull()
        ->and($filter->sort)->toBe('played')
        ->and($filter->page)->toBe(1);
});

it('ignores a search shorter than two characters', function () {
    expect(filterFor(['q' => 'a'])->q)->toBeNull();
});

it('keeps and trims a search of two characters or more', function () {
    expect(filterFor(['q' => '  fm  '])->q)->toBe('fm');
});

it('ignores a genre slug that does not exist', function () {
    expect(filterFor(['genre' => 'nonsense'])->genre)->toBeNull();
});

it('keeps a genre slug that exists', function () {
    expect(filterFor(['genre' => 'pop'])->genre)->toBe('pop');
});

it('ignores an unknown sort and falls back to played', function () {
    expect(filterFor(['sort' => 'nonsense'])->sort)->toBe('played');
});

it('accepts every documented sort', function () {
    foreach (['played', 'voted', 'bitrate', 'az'] as $sort) {
        expect(filterFor(['sort' => $sort])->sort)->toBe($sort);
    }
});

it('clamps a non-positive page to one', function () {
    expect(filterFor(['page' => '0'])->page)->toBe(1)
        ->and(filterFor(['page' => '-5'])->page)->toBe(1);
});

it('filters stations by title', function () {
    Radio::factory()->create(['title' => 'Jazz FM']);
    Radio::factory()->create(['title' => 'Rock FM']);

    $titles = filterFor(['q' => 'jazz'])->query()->pluck('title');

    expect($titles->all())->toBe(['Jazz FM']);
});

it('filters stations by genre', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();

    $jazzStation = Radio::factory()->create(['title' => 'Jazz Station']);
    $jazzStation->genres()->attach($jazz);

    Radio::factory()->create(['title' => 'Pop Station']);

    $titles = filterFor(['genre' => 'jazz'])->query()->pluck('title');

    expect($titles->all())->toBe(['Jazz Station']);
});

it('sorts a-to-z by title', function () {
    Radio::factory()->create(['title' => 'Zebra FM']);
    Radio::factory()->create(['title' => 'Alpha FM']);

    $titles = filterFor(['sort' => 'az'])->query()->pluck('title');

    expect($titles->all())->toBe(['Alpha FM', 'Zebra FM']);
});

it('sorts by votes when asked for voted', function () {
    Radio::factory()->create(['title' => 'Low', 'votes' => 1]);
    Radio::factory()->create(['title' => 'High', 'votes' => 99]);

    expect(filterFor(['sort' => 'voted'])->query()->pluck('title')->first())->toBe('High');
});

it('reports whether any non-default filter is active', function () {
    expect(filterFor([])->isActive())->toBeFalse()
        ->and(filterFor(['q' => 'fm'])->isActive())->toBeTrue()
        ->and(filterFor(['genre' => 'pop'])->isActive())->toBeTrue()
        ->and(filterFor(['sort' => 'az'])->isActive())->toBeTrue();
});
