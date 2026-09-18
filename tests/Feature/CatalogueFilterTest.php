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
    foreach (['played', 'popular', 'voted', 'bitrate', 'az', 'country'] as $sort) {
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

it('builds via fromRequest identically to the constructor', function () {
    $request = Request::create('/', 'GET', ['q' => 'fm']);

    expect(CatalogueFilter::fromRequest($request)->q)->toBe('fm');
});

it('defaults alive to true and fav to false', function () {
    $filter = filterFor([]);

    expect($filter->alive)->toBeTrue()
        ->and($filter->fav)->toBeFalse();
});

it('turns alive off only for an explicit 0', function () {
    expect(filterFor(['alive' => '0'])->alive)->toBeFalse()
        ->and(filterFor(['alive' => '1'])->alive)->toBeTrue()
        ->and(filterFor(['alive' => 'bogus'])->alive)->toBeTrue();
});

it('turns fav on for a truthy value', function () {
    expect(filterFor(['fav' => '1'])->fav)->toBeTrue()
        ->and(filterFor([])->fav)->toBeFalse();
});

it('keeps a two-letter country code, uppercased', function () {
    expect(filterFor(['country' => 'de'])->country)->toBe('DE');
});

it('ignores a malformed country code', function () {
    expect(filterFor(['country' => 'zzz'])->country)->toBeNull()
        ->and(filterFor(['country' => '1'])->country)->toBeNull();
});

it('keeps a known continent as region', function () {
    expect(filterFor(['region' => 'europe'])->region)->toBe('europe');
});

it('ignores an unknown region', function () {
    expect(filterFor(['region' => 'narnia'])->region)->toBeNull();
});

it('lets country win when country and region both arrive', function () {
    $filter = filterFor(['country' => 'de', 'region' => 'asia']);

    expect($filter->country)->toBe('DE')
        ->and($filter->region)->toBeNull();
});

it('trims language and ignores an empty one', function () {
    expect(filterFor(['language' => '  english  '])->language)->toBe('english')
        ->and(filterFor(['language' => '   '])->language)->toBeNull();
});

it('exposes removable filter chips with a url that drops just that param', function () {
    $filter = filterFor(['q' => 'fm', 'genre' => 'jazz']);

    $tokens = collect($filter->tokens())->keyBy('key');

    expect($tokens->has('q'))->toBeTrue()
        ->and($tokens->has('genre'))->toBeTrue()
        ->and($tokens['genre']['label'])->toBe('Jazz')
        ->and($tokens['q']['url'])->not->toContain('q=fm')
        ->and($tokens['q']['url'])->toContain('genre=jazz')
        ->and($tokens['genre']['url'])->not->toContain('genre=jazz')
        ->and($tokens['genre']['url'])->toContain('q=fm');
});

it('produces no chips when no filter is active', function () {
    expect(filterFor([])->tokens())->toBe([]);
});

it('builds a minimal canonical query string, omitting defaults', function () {
    expect(filterFor([])->toQueryString())->toBe('')
        ->and(filterFor(['sort' => 'az'])->toQueryString())->toBe('sort=az')
        ->and(filterFor(['page' => '3'])->toQueryString())->toBe('page=3');
});

it('lets overrides replace or remove params in the query string', function () {
    $filter = filterFor(['genre' => 'jazz', 'page' => '2']);

    expect($filter->toQueryString(['page' => 5]))->toBe('genre=jazz&page=5')
        ->and($filter->toQueryString(['page' => null]))->toBe('genre=jazz');
});
