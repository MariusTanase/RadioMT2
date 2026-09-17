<?php

use App\Models\Radio;
use Database\Seeders\RadioSeeder;

it('serves the radio app at the root url', function () {
    $this->seed(RadioSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertViewIs('radio.index')
        ->assertViewHas('radios');
});

it('passes every seeded station to the view', function () {
    $this->seed(RadioSeeder::class);

    expect($this->get('/')->viewData('radios'))->toHaveCount(20);
});

it('renders the station titles into the page', function () {
    $this->seed(RadioSeeder::class);

    $response = $this->get('/');

    foreach (Radio::pluck('title') as $title) {
        $response->assertSee($title, escape: true);
    }
});

it('renders without stations rather than erroring', function () {
    $this->get('/')->assertOk();
});
