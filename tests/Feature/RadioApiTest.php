<?php

use Database\Seeders\RadioSeeder;

it('lists every station as json', function () {
    $this->seed(RadioSeeder::class);

    $this->getJson('/api/radios')->assertOk()->assertJsonCount(20);
});

it('exposes exactly the station fields the player needs', function () {
    $this->seed(RadioSeeder::class);

    $this->getJson('/api/radios')->assertJsonStructure([
        '*' => ['id', 'title', 'artist', 'genre', 'image', 'url'],
    ]);
});

it('returns stations ordered by id', function () {
    $this->seed(RadioSeeder::class);

    $ids = collect($this->getJson('/api/radios')->json())->pluck('id')->all();

    expect($ids)->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21]);
});

it('returns an empty array when the library is empty', function () {
    $this->getJson('/api/radios')->assertOk()->assertExactJson([]);
});
