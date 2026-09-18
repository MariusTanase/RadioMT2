<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Radio Browser import fixtures
|--------------------------------------------------------------------------
|
| Shared by tests/Feature/RadioImport*.php. They live here — rather than in
| one of those test files — because Pest loads every test file as it runs
| it, so a function declared inside one test file is only safely visible to
| files that execute after it; tests/Pest.php is loaded once, up front, so
| these are available to every test regardless of run order.
|
*/

/** @return array<string, mixed> a single Radio Browser API station record */
function station(array $overrides = []): array
{
    return array_merge([
        'stationuuid' => 'uuid-1',
        'name' => 'Test FM',
        'url' => 'https://example.test/stream',
        'url_resolved' => 'https://example.test/resolved',
        'homepage' => 'https://example.test',
        'favicon' => 'https://example.test/icon.png',
        'tags' => 'pop,rock',
        'country' => 'Germany',
        'countrycode' => 'DE',
        'language' => 'german',
        'codec' => 'MP3',
        'bitrate' => 128,
        'votes' => 42,
        'clickcount' => 99,
        'lastcheckok' => 1,
        'lastchecktime_iso8601' => '2026-09-16T13:32:14Z',
    ], $overrides);
}

/** @return list<array<string, mixed>> $count stations numbered from $from */
function page(int $from, int $count): array
{
    return collect(range($from, $from + $count - 1))
        ->map(fn ($i) => station(['stationuuid' => "uuid-$i", 'name' => "Station $i"]))
        ->all();
}

/**
 * Fake the Radio Browser endpoint with one response per page, in order.
 *
 * Pushes exactly one response per array given, then falls back to an empty
 * page for any further request. The `whenEmpty()` fallback matters because
 * several import tests run `radios:import` twice against the same fake: a
 * plain `pushResponse(...$pages)` splat (as an earlier draft of this helper
 * did) silently drops every page but the first, since `pushResponse()`
 * takes exactly one argument — and a bare `ResponseSequence` throws
 * `OutOfBoundsException` once its queue is exhausted, rather than idling.
 */
function fakePages(array ...$pages): void
{
    $sequence = Http::sequence();

    foreach ($pages as $page) {
        $sequence->push($page);
    }

    Http::fake(['*radio-browser.info*' => $sequence->whenEmpty(Http::response([]))]);
}
