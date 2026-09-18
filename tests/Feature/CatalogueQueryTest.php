<?php

use App\Models\Genre;
use App\Models\Radio;
use App\Support\CatalogueFilter;
use Database\Seeders\GenreSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

beforeEach(fn () => $this->seed(GenreSeeder::class));

function queryFor(array $query): Builder
{
    return CatalogueFilter::fromRequest(Request::create('/', 'GET', $query))->query();
}

it('excludes dead stations by default', function () {
    Radio::factory()->create(['title' => 'Alive', 'is_alive' => true]);
    Radio::factory()->create(['title' => 'Dead', 'is_alive' => false]);

    expect(queryFor([])->pluck('title')->all())->toBe(['Alive']);
});

it('includes dead stations when alive=0', function () {
    Radio::factory()->create(['title' => 'Alive', 'is_alive' => true]);
    Radio::factory()->create(['title' => 'Dead', 'is_alive' => false]);

    expect(queryFor(['alive' => '0'])->pluck('title')->sort()->values()->all())
        ->toBe(['Alive', 'Dead']);
});

it('restricts to favourites when fav=1', function () {
    Radio::factory()->create(['title' => 'Fav', 'is_favourite' => true]);
    Radio::factory()->create(['title' => 'Not Fav', 'is_favourite' => false]);

    expect(queryFor(['fav' => '1'])->pluck('title')->all())->toBe(['Fav']);
});

it('filters by exact country code', function () {
    Radio::factory()->create(['title' => 'German', 'country_code' => 'DE']);
    Radio::factory()->create(['title' => 'French', 'country_code' => 'FR']);

    expect(queryFor(['country' => 'de'])->pluck('title')->all())->toBe(['German']);
});

it('expands a region to every country code in that continent', function () {
    Radio::factory()->create(['title' => 'German', 'country_code' => 'DE']); // europe
    Radio::factory()->create(['title' => 'Japanese', 'country_code' => 'JP']); // asia
    Radio::factory()->create(['title' => 'No Country', 'country_code' => null]);

    expect(queryFor(['region' => 'europe'])->pluck('title')->all())->toBe(['German']);
});

it('groups codes absent from the continent map into the unknown region', function () {
    Radio::factory()->create(['title' => 'Mapped', 'country_code' => 'DE']);
    Radio::factory()->create(['title' => 'Unmapped', 'country_code' => 'ZZ']);
    Radio::factory()->create(['title' => 'No Country', 'country_code' => null]);

    expect(queryFor(['region' => 'unknown'])->pluck('title')->all())->toBe(['Unmapped']);
});

it('filters by exact language', function () {
    Radio::factory()->create(['title' => 'English', 'language' => 'english']);
    Radio::factory()->create(['title' => 'French', 'language' => 'french']);

    expect(queryFor(['language' => 'english'])->pluck('title')->all())->toBe(['English']);
});

it('ANDs every active filter together', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();

    $match = Radio::factory()->create([
        'title' => 'Berlin Jazz FM', 'country_code' => 'DE', 'language' => 'german', 'is_alive' => true,
    ]);
    $match->genres()->attach($jazz);

    $wrongGenre = Radio::factory()->create([
        'title' => 'Berlin Pop FM', 'country_code' => 'DE', 'language' => 'german', 'is_alive' => true,
    ]);

    $wrongCountry = Radio::factory()->create([
        'title' => 'Paris Jazz FM', 'country_code' => 'FR', 'language' => 'german', 'is_alive' => true,
    ]);
    $wrongCountry->genres()->attach($jazz);

    $titles = queryFor([
        'q' => 'jazz', 'genre' => 'jazz', 'country' => 'DE', 'language' => 'german',
    ])->pluck('title');

    expect($titles->all())->toBe(['Berlin Jazz FM']);
});

it('tiebreaks the played sort on id when click_count ties', function () {
    Radio::factory()->create(['id' => 10, 'title' => 'Ten', 'click_count' => 5, 'votes' => 5]);
    Radio::factory()->create(['id' => 5, 'title' => 'Five', 'click_count' => 5, 'votes' => 5]);
    Radio::factory()->create(['id' => 7, 'title' => 'Seven', 'click_count' => 5, 'votes' => 5]);

    expect(queryFor(['sort' => 'played'])->pluck('id')->all())->toBe([5, 7, 10]);
});

it('tiebreaks the voted sort on id when votes tie', function () {
    Radio::factory()->create(['id' => 30, 'title' => 'A', 'votes' => 9, 'click_count' => 1]);
    Radio::factory()->create(['id' => 10, 'title' => 'B', 'votes' => 9, 'click_count' => 9]);
    Radio::factory()->create(['id' => 20, 'title' => 'C', 'votes' => 9, 'click_count' => 9]);

    // click_count breaks the votes tie first (B and C both win over A), then id.
    expect(queryFor(['sort' => 'voted'])->pluck('id')->all())->toBe([10, 20, 30]);
});

it('tiebreaks the bitrate sort on id when bitrate ties', function () {
    Radio::factory()->create(['id' => 3, 'title' => 'A', 'bitrate' => 128, 'click_count' => 1]);
    Radio::factory()->create(['id' => 1, 'title' => 'B', 'bitrate' => 128, 'click_count' => 1]);
    Radio::factory()->create(['id' => 2, 'title' => 'C', 'bitrate' => 128, 'click_count' => 1]);

    expect(queryFor(['sort' => 'bitrate'])->pluck('id')->all())->toBe([1, 2, 3]);
});

it('tiebreaks the az sort on id when titles tie', function () {
    Radio::factory()->create(['id' => 9, 'title' => 'Same']);
    Radio::factory()->create(['id' => 4, 'title' => 'Same']);

    expect(queryFor(['sort' => 'az'])->pluck('id')->all())->toBe([4, 9]);
});

it('sorts by country ascending, most-played first within each country, nulls last, tiebreaking on id', function () {
    Radio::factory()->create(['id' => 1, 'title' => 'FR high', 'country' => 'France', 'click_count' => 50]);
    Radio::factory()->create(['id' => 2, 'title' => 'FR low', 'country' => 'France', 'click_count' => 5]);
    Radio::factory()->create(['id' => 3, 'title' => 'DE only', 'country' => 'Germany', 'click_count' => 1]);
    Radio::factory()->create(['id' => 5, 'title' => 'No country b', 'country' => null, 'click_count' => 99]);
    Radio::factory()->create(['id' => 4, 'title' => 'No country a', 'country' => null, 'click_count' => 99]);

    // France < Germany alphabetically; within France, higher click_count first;
    // null country sorts after every real country; id tiebreaks the null pair.
    expect(queryFor(['sort' => 'country'])->pluck('id')->all())->toBe([1, 2, 3, 4, 5]);
});
