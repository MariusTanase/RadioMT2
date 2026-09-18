<?php

use App\Models\Genre;
use App\Models\Radio;
use Database\Seeders\GenreSeeder;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('returns a station from the filtered set as json', function () {
    $station = Radio::factory()->create(['title' => 'Only One']);

    $this->getJson('/api/catalogue/random')
        ->assertOk()
        ->assertJson([
            'id' => $station->id,
            'title' => 'Only One',
            'url' => $station->url,
            'genre' => $station->genre,
            'image' => $station->image,
        ]);
});

it('honours active filters, never returning a station outside the tuned set', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();
    $jazzStation = Radio::factory()->create(['title' => 'Jazz Station']);
    $jazzStation->genres()->attach($jazz);

    Radio::factory()->count(10)->create(['title' => 'Pop Station']);

    $this->getJson('/api/catalogue/random?genre=jazz')
        ->assertOk()
        ->assertJson(['id' => $jazzStation->id]);
});

it('returns 404 when the filtered set is empty', function () {
    Radio::factory()->create(['title' => 'Rock FM']);

    $this->getJson('/api/catalogue/random?q=nonexistent')->assertNotFound();
});

it('returns 404 when the library is empty', function () {
    $this->getJson('/api/catalogue/random')->assertNotFound();
});
