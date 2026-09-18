<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('imports a station with every field mapped', function () {
    fakePages([station()]);

    $this->artisan('radios:import')->assertSuccessful();

    $radio = Radio::where('station_uuid', 'uuid-1')->firstOrFail();

    expect($radio->title)->toBe('Test FM')
        ->and($radio->country)->toBe('Germany')
        ->and($radio->country_code)->toBe('DE')
        ->and($radio->codec)->toBe('MP3')
        ->and($radio->bitrate)->toBe(128)
        ->and($radio->votes)->toBe(42)
        ->and($radio->click_count)->toBe(99)
        ->and($radio->source)->toBe('radiobrowser')
        ->and($radio->is_favourite)->toBeFalse()
        ->and($radio->is_alive)->toBeTrue()
        ->and($radio->checked_at)->not->toBeNull();
});

it('prefers the resolved url over the raw url', function () {
    fakePages([station()]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('url'))
        ->toBe('https://example.test/resolved');
});

it('falls back to the raw url when there is no resolved url', function () {
    fakePages([station(['url_resolved' => ''])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('url'))
        ->toBe('https://example.test/stream');
});

it('skips stations with no usable url', function () {
    fakePages([station(['url' => '', 'url_resolved' => ''])]);

    $this->artisan('radios:import');

    expect(Radio::where('source', 'radiobrowser')->count())->toBe(0);
});

it('trims the tab characters real station names arrive with', function () {
    fakePages([station(['name' => "\t\tLOVE FM: All you need  "])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('title'))
        ->toBe('LOVE FM: All you need');
});

it('attaches every mapped genre to the station', function () {
    fakePages([station(['tags' => 'pop,rock'])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->firstOrFail()->genres->pluck('slug')->sort()->values()->all())
        ->toBe(['pop', 'rock']);
});

it('files an unmappable station under other', function () {
    fakePages([station(['tags' => 'moi merino'])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->firstOrFail()->genres->pluck('slug')->all())
        ->toBe(['other']);
});

it('records a dead station as not alive', function () {
    fakePages([station(['lastcheckok' => 0])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('is_alive'))->toBeFalsy();
});
