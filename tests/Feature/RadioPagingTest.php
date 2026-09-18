<?php

use App\Models\Genre;
use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('shows only one page of stations at a time', function () {
    Radio::factory()->count(120)->create();

    expect($this->get('/')->viewData('radios'))->toHaveCount(50);
});

it('paginates to a second page without repeating a station', function () {
    Radio::factory()->count(120)->create();

    $first = $this->get('/')->viewData('radios')->pluck('id');
    $second = $this->get('/?page=2')->viewData('radios')->pluck('id');

    expect($first->intersect($second))->toBeEmpty()
        ->and($second)->toHaveCount(50);
});

it('renders pagination controls when there is more than one page', function () {
    Radio::factory()->count(120)->create();

    $this->get('/')->assertSee('Page 1 of', escape: false);
});

it('does not render pagination controls for a single page', function () {
    $this->seed(RadioSeeder::class);

    $this->get('/')->assertDontSee('Page 1 of', escape: false);
});

it('filters the page by genre', function () {
    $jazz = Genre::where('slug', 'jazz')->firstOrFail();
    $jazzStation = Radio::factory()->create(['title' => 'Jazz One']);
    $jazzStation->genres()->attach($jazz);
    Radio::factory()->create(['title' => 'Pop One']);

    $titles = $this->get('/?genre=jazz')->viewData('radios')->pluck('title');

    expect($titles->all())->toBe(['Jazz One']);
});

it('filters the page by search term', function () {
    Radio::factory()->create(['title' => 'Jazz FM']);
    Radio::factory()->create(['title' => 'Rock FM']);

    $titles = $this->get('/?q=jazz')->viewData('radios')->pluck('title');

    expect($titles->all())->toBe(['Jazz FM']);
});

it('shows an empty state rather than an empty list when nothing matches', function () {
    Radio::factory()->create(['title' => 'Rock FM']);

    $this->get('/?q=nonexistent')->assertSee('No stations match your filters.');
});

it('shows the genre and sort menu', function () {
    $this->get('/')->assertSee('All genres')->assertSee('Most played');
});
