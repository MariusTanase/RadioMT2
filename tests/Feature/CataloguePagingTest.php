<?php

use App\Models\Radio;
use App\Support\CatalogueFilter;
use Database\Seeders\GenreSeeder;
use Illuminate\Http\Request;

beforeEach(fn () => $this->seed(GenreSeeder::class));

function paginateFor(array $query)
{
    return CatalogueFilter::fromRequest(Request::create('/', 'GET', $query))->paginate();
}

it('shows 50 stations per page', function () {
    Radio::factory()->count(75)->create();

    expect(paginateFor([])->count())->toBe(50);
});

it('continues to the second page without repeating or skipping a station across a click_count tie', function () {
    // Every row ties on click_count, the exact condition that breaks paging
    // without an id tiebreaker: 60 rows > one 50-row page.
    Radio::factory()->count(60)->sequence(fn ($sequence) => ['id' => $sequence->index + 1])
        ->create(['click_count' => 7, 'votes' => 7]);

    $first = paginateFor(['sort' => 'played'])->pluck('id');
    $second = paginateFor(['sort' => 'played', 'page' => 2])->pluck('id');

    expect($first)->toHaveCount(50)
        ->and($second)->toHaveCount(10)
        ->and($first->intersect($second))->toBeEmpty()
        ->and($first->concat($second)->sort()->values()->all())->toBe(range(1, 60));
});

it('continues to the second page without repeating or skipping a station when click_count and title both tie', function () {
    // Every row ties on click_count AND title — the case that only the id
    // tiebreak (the true final, unique column after title) can resolve.
    Radio::factory()->count(60)->sequence(fn ($sequence) => ['id' => $sequence->index + 1])
        ->create(['click_count' => 7, 'votes' => 7, 'title' => 'Same Station']);

    $first = paginateFor(['sort' => 'played'])->pluck('id');
    $second = paginateFor(['sort' => 'played', 'page' => 2])->pluck('id');

    expect($first)->toHaveCount(50)
        ->and($second)->toHaveCount(10)
        ->and($first->intersect($second))->toBeEmpty()
        ->and($first->concat($second)->sort()->values()->all())->toBe(range(1, 60))
        // With every other column tied, id ASC is the only thing left to
        // order by, so the pages must come out in strict id order.
        ->and($first->all())->toBe(range(1, 50))
        ->and($second->all())->toBe(range(51, 60));
});

it('returns an empty manifest, not an error, for a page beyond the last', function () {
    Radio::factory()->count(5)->create();

    $page = paginateFor(['page' => 999]);

    expect($page->count())->toBe(0)
        ->and($page->items())->toBe([]);
});
