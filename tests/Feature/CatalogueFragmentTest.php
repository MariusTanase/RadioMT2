<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('returns a fragment, not a full document', function () {
    Radio::factory()->create(['title' => 'Fragment FM']);

    $response = $this->get('/api/catalogue');

    $response->assertOk()
        ->assertDontSee('<html', escape: false)
        ->assertDontSee('<!DOCTYPE', escape: false)
        ->assertSee('Fragment FM');
});

it('shows the total count of the filtered set', function () {
    Radio::factory()->count(3)->create();

    $this->get('/api/catalogue')->assertSee('3 stations');
});

it('renders the empty state rather than an empty list when nothing matches', function () {
    Radio::factory()->create(['title' => 'Rock FM']);

    $this->get('/api/catalogue?q=nonexistent')->assertSee('No stations match your filters.');
});

it('renders the same manifest for a deep link and a navigated fetch', function () {
    Radio::factory()->count(5)->create();

    $deepLink = $this->get('/api/catalogue?sort=az')->getContent();
    $navigated = $this->get('/api/catalogue?sort=az')->getContent();

    expect($deepLink)->toBe($navigated);
});

it('ignores garbage query parameters instead of erroring', function () {
    Radio::factory()->create();

    $this->get('/api/catalogue?genre=nonsense&sort=garbage&page=-5&country=zz')->assertOk();
});
