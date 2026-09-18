<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('does not duplicate stations when run twice', function () {
    // One page per run: each run's single station is well short of a full
    // page, so the command stops after its first fetch — the two artisan
    // calls below consume these two pages in order.
    fakePages([station()], [station()]);

    $this->artisan('radios:import');
    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->count())->toBe(1);
});

it('does not duplicate pivot rows when run twice', function () {
    fakePages([station(['tags' => 'pop,rock'])], [station(['tags' => 'pop,rock'])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import');

    expect(DB::table('genre_radio')->count())->toBe(2);
});

it('replaces stale genres rather than accumulating them', function () {
    fakePages([station(['tags' => 'pop,rock'])], [station(['tags' => 'jazz'])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->firstOrFail()->genres->pluck('slug')->all())
        ->toBe(['jazz']);
});

it('preserves locally probed data across a re-import', function () {
    fakePages([station()], [station()]);

    $this->artisan('radios:import');
    Radio::where('station_uuid', 'uuid-1')->update(['local_ok' => true, 'listeners' => 1234]);

    $this->artisan('radios:import');

    $radio = Radio::where('station_uuid', 'uuid-1')->firstOrFail();
    expect($radio->local_ok)->toBeTrue()->and($radio->listeners)->toBe(1234);
});
