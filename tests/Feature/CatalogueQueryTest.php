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

it('sorts played by play_count desc, then click_count desc, then title asc, then id asc', function () {
    Radio::factory()->create(['id' => 1, 'title' => 'Top', 'play_count' => 10, 'click_count' => 1]);
    Radio::factory()->create(['id' => 2, 'title' => 'B-High-Click', 'play_count' => 5, 'click_count' => 20]);
    Radio::factory()->create(['id' => 3, 'title' => 'C-Low-Click', 'play_count' => 5, 'click_count' => 5]);
    Radio::factory()->create(['id' => 20, 'title' => 'Zeta', 'play_count' => 0, 'click_count' => 0]);
    Radio::factory()->create(['id' => 10, 'title' => 'Alpha', 'play_count' => 0, 'click_count' => 0]);
    Radio::factory()->create(['id' => 30, 'title' => 'Alpha', 'play_count' => 0, 'click_count' => 0]);

    // id1 wins on play_count; id2 beats id3 on click_count once play_count
    // ties; the play_count=0/click_count=0 tail (today's typical row, since
    // nothing has incremented play_count yet) is ordered by title, with id
    // breaking the final Alpha/Alpha title tie.
    expect(queryFor(['sort' => 'played'])->pluck('id')->all())->toBe([1, 2, 3, 10, 30, 20]);
});

it('sorts popular by click_count desc, then votes desc, then title asc, then id asc', function () {
    Radio::factory()->create(['id' => 1, 'title' => 'Top', 'click_count' => 100, 'votes' => 1]);
    Radio::factory()->create(['id' => 2, 'title' => 'B-High-Votes', 'click_count' => 50, 'votes' => 20]);
    Radio::factory()->create(['id' => 3, 'title' => 'C-Low-Votes', 'click_count' => 50, 'votes' => 5]);
    Radio::factory()->create(['id' => 20, 'title' => 'Zeta', 'click_count' => 0, 'votes' => 0]);
    Radio::factory()->create(['id' => 10, 'title' => 'Alpha', 'click_count' => 0, 'votes' => 0]);
    Radio::factory()->create(['id' => 30, 'title' => 'Alpha', 'click_count' => 0, 'votes' => 0]);

    expect(queryFor(['sort' => 'popular'])->pluck('id')->all())->toBe([1, 2, 3, 10, 30, 20]);
});

it('sorts voted by votes desc, then click_count desc, then title asc, then id asc', function () {
    Radio::factory()->create(['id' => 1, 'title' => 'Top', 'votes' => 100, 'click_count' => 1]);
    Radio::factory()->create(['id' => 2, 'title' => 'B-High-Click', 'votes' => 50, 'click_count' => 20]);
    Radio::factory()->create(['id' => 3, 'title' => 'C-Low-Click', 'votes' => 50, 'click_count' => 5]);
    Radio::factory()->create(['id' => 20, 'title' => 'Zeta', 'votes' => 0, 'click_count' => 0]);
    Radio::factory()->create(['id' => 10, 'title' => 'Alpha', 'votes' => 0, 'click_count' => 0]);
    Radio::factory()->create(['id' => 30, 'title' => 'Alpha', 'votes' => 0, 'click_count' => 0]);

    expect(queryFor(['sort' => 'voted'])->pluck('id')->all())->toBe([1, 2, 3, 10, 30, 20]);
});

it('sorts bitrate by bitrate desc, then click_count desc, then title asc, then id asc', function () {
    Radio::factory()->create(['id' => 1, 'title' => 'Top', 'bitrate' => 320, 'click_count' => 1]);
    Radio::factory()->create(['id' => 2, 'title' => 'B-High-Click', 'bitrate' => 128, 'click_count' => 20]);
    Radio::factory()->create(['id' => 3, 'title' => 'C-Low-Click', 'bitrate' => 128, 'click_count' => 5]);
    Radio::factory()->create(['id' => 20, 'title' => 'Zeta', 'bitrate' => 64, 'click_count' => 0]);
    Radio::factory()->create(['id' => 10, 'title' => 'Alpha', 'bitrate' => 64, 'click_count' => 0]);
    Radio::factory()->create(['id' => 30, 'title' => 'Alpha', 'bitrate' => 64, 'click_count' => 0]);

    expect(queryFor(['sort' => 'bitrate'])->pluck('id')->all())->toBe([1, 2, 3, 10, 30, 20]);
});

it('tiebreaks the az sort on id when titles tie', function () {
    Radio::factory()->create(['id' => 9, 'title' => 'Same']);
    Radio::factory()->create(['id' => 4, 'title' => 'Same']);

    expect(queryFor(['sort' => 'az'])->pluck('id')->all())->toBe([4, 9]);
});

it('sorts by country ascending, most-played first within each country, then title, then id', function () {
    Radio::factory()->create(['id' => 1, 'title' => 'FR-Top', 'country' => 'France', 'click_count' => 50]);
    Radio::factory()->create(['id' => 2, 'title' => 'FR-B', 'country' => 'France', 'click_count' => 20]);
    Radio::factory()->create(['id' => 3, 'title' => 'FR-C', 'country' => 'France', 'click_count' => 20]);
    Radio::factory()->create(['id' => 4, 'title' => 'DE-only', 'country' => 'Germany', 'click_count' => 999]);
    Radio::factory()->create(['id' => 20, 'title' => 'NullZeta', 'country' => null, 'click_count' => 0]);
    Radio::factory()->create(['id' => 10, 'title' => 'NullAlpha', 'country' => null, 'click_count' => 0]);
    Radio::factory()->create(['id' => 30, 'title' => 'NullAlpha', 'country' => null, 'click_count' => 0]);

    // France < Germany alphabetically; within France, higher click_count
    // first, then title breaks the id2/id3 click_count tie; null country
    // sorts after every real country; title then id order the null group.
    expect(queryFor(['sort' => 'country'])->pluck('id')->all())->toBe([1, 2, 3, 4, 10, 30, 20]);
});
