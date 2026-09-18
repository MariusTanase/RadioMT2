<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Illuminate\Support\Facades\DB;

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

it('increments play_count on the ok endpoint', function () {
    $radio = Radio::factory()->create(['play_count' => 0]);

    $this->postJson("/api/stations/{$radio->id}/ok")->assertNoContent();

    expect($radio->fresh()->play_count)->toBe(1);
});

it('accumulates play_count across repeated ok calls rather than overwriting it', function () {
    $radio = Radio::factory()->create(['play_count' => 5]);

    $this->postJson("/api/stations/{$radio->id}/ok")->assertNoContent();
    $this->postJson("/api/stations/{$radio->id}/ok")->assertNoContent();
    $this->postJson("/api/stations/{$radio->id}/ok")->assertNoContent();

    expect($radio->fresh()->play_count)->toBe(8);
});

it('does not increment play_count on the unreachable endpoint', function () {
    $radio = Radio::factory()->create(['play_count' => 3]);

    $this->postJson("/api/stations/{$radio->id}/unreachable")->assertNoContent();

    expect($radio->fresh()->play_count)->toBe(3);
});

it('increments play_count atomically, via a column expression rather than read-modify-write', function () {
    $radio = Radio::factory()->create(['play_count' => 0]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->postJson("/api/stations/{$radio->id}/ok")->assertNoContent();

    $incrementQuery = collect($queries)->first(
        fn (string $sql) => str_contains($sql, 'play_count') && preg_match('/=\s*"?play_count"?\s*\+/i', $sql) === 1
    );

    // An atomic increment is a single UPDATE whose SET clause references
    // play_count on the right-hand side (play_count = play_count + 1).
    // A read-modify-write would instead SELECT the current value and issue
    // an UPDATE with a literal — losing updates under concurrent requests.
    expect($incrementQuery)->not->toBeNull();
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
