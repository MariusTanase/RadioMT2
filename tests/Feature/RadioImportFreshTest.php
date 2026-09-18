<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;

beforeEach(function () {
    $this->seed(GenreSeeder::class);
    $this->seed(RadioSeeder::class);
});

it('removes previously imported stations', function () {
    // One page per run — see RadioImportIdempotencyTest for why.
    fakePages([station(['stationuuid' => 'old'])], [station(['stationuuid' => 'new'])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import', ['--fresh' => true]);

    expect(Radio::where('station_uuid', 'old')->exists())->toBeFalse()
        ->and(Radio::where('station_uuid', 'new')->exists())->toBeTrue();
});

it('never deletes the seeded favourites', function () {
    fakePages([station()]);

    $this->artisan('radios:import', ['--fresh' => true])->assertSuccessful();

    expect(Radio::where('source', 'seed')->count())->toBe(20)
        ->and(Radio::orderBy('id')->pluck('id')->take(7)->all())
        ->toBe([1, 2, 3, 4, 5, 6, 8]);
});
