<?php

use App\Models\Genre;
use App\Models\Radio;
use App\Support\CatalogueCounts;
use App\Support\CatalogueFilter;
use Database\Seeders\GenreSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('caches genre counts for alive stations only', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();
    $pop = Genre::where('slug', 'pop')->firstOrFail();

    Radio::factory()->create(['is_alive' => true])->genres()->attach($jazz);
    Radio::factory()->create(['is_alive' => true])->genres()->attach($jazz);
    Radio::factory()->create(['is_alive' => false])->genres()->attach($jazz);
    Radio::factory()->create(['is_alive' => true])->genres()->attach($pop);

    expect(CatalogueCounts::genreCounts())->toBe(['jazz' => 2, 'pop' => 1])
        ->and(Cache::has(CatalogueCounts::GENRE_KEY))->toBeTrue();
});

it('serves genre counts from cache without re-querying once cached', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();
    Radio::factory()->create(['is_alive' => true])->genres()->attach($jazz);

    expect(CatalogueCounts::genreCounts())->toBe(['jazz' => 1]);

    // A new station appears, but the cached figure must not move until flushed.
    Radio::factory()->create(['is_alive' => true])->genres()->attach($jazz);

    expect(CatalogueCounts::genreCounts())->toBe(['jazz' => 1]);
});

it('caches country counts for alive stations only', function () {
    Radio::factory()->create(['country_code' => 'DE', 'is_alive' => true]);
    Radio::factory()->create(['country_code' => 'DE', 'is_alive' => true]);
    Radio::factory()->create(['country_code' => 'DE', 'is_alive' => false]);
    Radio::factory()->create(['country_code' => null, 'is_alive' => true]);

    expect(CatalogueCounts::countryCounts())->toBe(['DE' => 2]);
});

it('caches language counts across all stations', function () {
    Radio::factory()->create(['language' => 'english']);
    Radio::factory()->create(['language' => 'english']);
    Radio::factory()->create(['language' => 'french']);
    Radio::factory()->create(['language' => null]);

    expect(CatalogueCounts::languages())->toBe(['english' => 2, 'french' => 1]);
});

it('flushes all three cached aggregates', function () {
    Radio::factory()->create(['language' => 'english', 'country_code' => 'DE']);

    CatalogueCounts::languages();
    CatalogueCounts::countryCounts();
    CatalogueCounts::genreCounts();

    expect(Cache::has(CatalogueCounts::GENRE_KEY))->toBeTrue()
        ->and(Cache::has(CatalogueCounts::COUNTRY_KEY))->toBeTrue()
        ->and(Cache::has(CatalogueCounts::LANGUAGE_KEY))->toBeTrue();

    CatalogueCounts::flush();

    expect(Cache::has(CatalogueCounts::GENRE_KEY))->toBeFalse()
        ->and(Cache::has(CatalogueCounts::COUNTRY_KEY))->toBeFalse()
        ->and(Cache::has(CatalogueCounts::LANGUAGE_KEY))->toBeFalse();
});

it('flushes the caches when an import completes', function () {
    CatalogueCounts::genreCounts();
    expect(Cache::has(CatalogueCounts::GENRE_KEY))->toBeTrue();

    fakePages([station()]);
    $this->artisan('radios:import')->assertSuccessful();

    expect(Cache::has(CatalogueCounts::GENRE_KEY))->toBeFalse();
});

it('computes scoped genre counts for the active filter in one grouped query', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();
    $pop = Genre::where('slug', 'pop')->firstOrFail();

    $germanJazz = Radio::factory()->create(['country_code' => 'DE', 'is_alive' => true]);
    $germanJazz->genres()->attach($jazz);

    $germanPop = Radio::factory()->create(['country_code' => 'DE', 'is_alive' => true]);
    $germanPop->genres()->attach($pop);

    $frenchJazz = Radio::factory()->create(['country_code' => 'FR', 'is_alive' => true]);
    $frenchJazz->genres()->attach($jazz);

    $filter = CatalogueFilter::fromRequest(Request::create('/', 'GET', ['country' => 'DE']));

    expect(CatalogueCounts::scopedGenreCounts($filter))->toBe(['jazz' => 1, 'pop' => 1]);
});

it('scoped genre counts exclude the active genre filter itself from the scope', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();
    $pop = Genre::where('slug', 'pop')->firstOrFail();

    $germanJazz = Radio::factory()->create(['country_code' => 'DE', 'is_alive' => true]);
    $germanJazz->genres()->attach($jazz);

    $germanPop = Radio::factory()->create(['country_code' => 'DE', 'is_alive' => true]);
    $germanPop->genres()->attach($pop);

    // Filtering by genre=jazz should not zero out every other genre's count —
    // the facet counts must be computed against the *other* filters only.
    $filter = CatalogueFilter::fromRequest(Request::create('/', 'GET', ['country' => 'DE', 'genre' => 'jazz']));

    expect(CatalogueCounts::scopedGenreCounts($filter))->toBe(['jazz' => 1, 'pop' => 1]);
});
