<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('keeps paging until a short page arrives', function () {
    fakePages(page(1, 1000), page(1001, 5));

    $this->artisan('radios:import')->assertSuccessful();

    expect(Radio::where('source', 'radiobrowser')->count())->toBe(1005);
});

it('stops at the requested limit', function () {
    fakePages(page(1, 1000));

    $this->artisan('radios:import', ['--limit' => 10])->assertSuccessful();

    expect(Radio::where('source', 'radiobrowser')->count())->toBe(10);
});

it('passes the country filter to the api', function () {
    fakePages([]);

    $this->artisan('radios:import', ['--country' => 'RO']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'countrycode=RO'));
});

it('requests a stable ordering, never clickcount', function () {
    fakePages([]);

    $this->artisan('radios:import');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'order=name')
        && str_contains($request->url(), 'hidebroken=true'));
});

it('identifies itself with a descriptive user agent', function () {
    fakePages([]);

    $this->artisan('radios:import');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'RadioMT2'));
});

it('fails loudly when a page cannot be fetched', function () {
    Http::fake(['*radio-browser.info*' => Http::response(status: 500)]);

    $this->artisan('radios:import')->assertFailed();
});
