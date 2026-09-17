<?php

use App\Models\Radio;
use Database\Seeders\RadioSeeder;

it('seeds exactly twenty stations', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::count())->toBe(20);
});

it('preserves the original ids, including the gap at seven', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::orderBy('id')->pluck('id')->all())
        ->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21]);
});

it('can be seeded twice without duplicating stations', function () {
    $this->seed(RadioSeeder::class);
    $this->seed(RadioSeeder::class);

    expect(Radio::count())->toBe(20);
});

it('stores every field of a station', function () {
    $this->seed(RadioSeeder::class);

    $station = Radio::find(1);

    expect($station->title)->toBe('Virgin FM')
        ->and($station->artist)->toBe('Pop')
        ->and($station->genre)->toBe('Pop')
        ->and($station->url)->toBe('https://radio.virginradio.co.uk/stream')
        ->and($station->image)->toStartWith('https://');
});

it('orders stations by id to match the original array order', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::orderBy('id')->first()->title)->toBe('Virgin FM')
        ->and(Radio::orderBy('id')->get()->last()->title)->toBe('BOX : K-POP');
});
