<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('marks a station unreachable without touching is_alive', function () {
    $radio = Radio::factory()->create(['local_ok' => null, 'is_alive' => true]);

    $this->postJson("/api/stations/{$radio->id}/unreachable")->assertNoContent();

    $radio->refresh();

    expect($radio->local_ok)->toBeFalse()
        ->and($radio->local_checked_at)->not->toBeNull()
        ->and($radio->is_alive)->toBeTrue();
});

it('marks a station ok without touching is_alive', function () {
    $radio = Radio::factory()->create(['local_ok' => false, 'is_alive' => false]);

    $this->postJson("/api/stations/{$radio->id}/ok")->assertNoContent();

    $radio->refresh();

    expect($radio->local_ok)->toBeTrue()
        ->and($radio->local_checked_at)->not->toBeNull()
        ->and($radio->is_alive)->toBeFalse();
});

it('returns 204 for an unknown station id, telling a probing client nothing', function () {
    $this->postJson('/api/stations/999999/unreachable')->assertNoContent();
    $this->postJson('/api/stations/999999/ok')->assertNoContent();
});

it('throttles past 20 requests per minute', function () {
    $radio = Radio::factory()->create();

    for ($i = 0; $i < 20; $i++) {
        $this->postJson("/api/stations/{$radio->id}/unreachable")->assertNoContent();
    }

    $this->postJson("/api/stations/{$radio->id}/unreachable")
        ->assertStatus(429);
});
