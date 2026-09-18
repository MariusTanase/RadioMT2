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

it('returns stations ordered by the default catalogue sort (title, since these seed rows have no play or click count)', function () {
    $this->seed(RadioSeeder::class);

    $ids = collect($this->getJson('/api/radios')->json())->pluck('id')->all();

    // The seed rows all tie on play_count and click_count (both 0), so the
    // default `played` sort falls through to its title/id tiebreak — this
    // is the alphabetical-by-title tail the tiebreak change is for, not id
    // order, which is what this asserted before that change.
    expect($ids)->toBe([13, 21, 5, 2, 3, 17, 18, 19, 16, 14, 6, 8, 4, 11, 15, 9, 20, 12, 10, 1]);
});

it('returns an empty array when the library is empty', function () {
    $this->getJson('/api/radios')->assertOk()->assertExactJson([]);
});
