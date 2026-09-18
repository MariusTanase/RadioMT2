<?php

use App\Jobs\ReadNowPlaying;
use App\Models\Radio;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** @see tests/Unit/IcyReaderTest.php for the byte-format helper this mirrors. */
function nowPlayingIcyBody(int $metaInt, string $title): string
{
    $meta = "StreamTitle='{$title}';";
    $padded = str_pad($meta, (int) (ceil(strlen($meta) / 16) * 16), "\0");

    return str_repeat('a', $metaInt).chr((int) (strlen($padded) / 16)).$padded;
}

it('serves cached now-playing data without ever touching the network', function () {
    $radio = Radio::factory()->create();
    Cache::put(ReadNowPlaying::cacheKey($radio->id), ['status' => 'ok', 'title' => 'Artist - Track'], now()->addSeconds(20));

    Http::fake(); // any request at all fails the test below

    $this->getJson("/api/stations/{$radio->id}/now-playing")
        ->assertOk()
        ->assertExactJson(['title' => 'Artist - Track', 'state' => 'playing', 'poll_after' => 15]);

    Http::assertNothingSent();
});

it('returns immediately on a cache miss with a null title, without blocking on the network', function () {
    $radio = Radio::factory()->create(['url' => 'https://example.test/stream']);

    // Deliberately slow-looking fake: proves the RESPONSE contract doesn't
    // depend on whether/when this ever resolves. If the endpoint blocked on
    // it, the JSON below would show a title instead of null.
    Http::fake(['*' => Http::response(nowPlayingIcyBody(8, 'Artist - Track'), 200, ['icy-metaint' => '8'])]);

    $this->getJson("/api/stations/{$radio->id}/now-playing")
        ->assertOk()
        ->assertExactJson(['title' => null, 'state' => 'connecting', 'poll_after' => 15]);
});

it('reports the unreachable and unsupported states with their own backoff', function () {
    $radio = Radio::factory()->create();

    Cache::put(ReadNowPlaying::cacheKey($radio->id), ['status' => 'unreachable', 'title' => null], now()->addMinutes(5));
    $this->getJson("/api/stations/{$radio->id}/now-playing")
        ->assertExactJson(['title' => null, 'state' => 'failed', 'poll_after' => 120]);

    Cache::put(ReadNowPlaying::cacheKey($radio->id), ['status' => 'unsupported', 'title' => null], now()->addMinutes(30));
    $this->getJson("/api/stations/{$radio->id}/now-playing")
        ->assertExactJson(['title' => null, 'state' => 'playing', 'poll_after' => 60]);
});

it('answers 204 for a station id that does not exist, telling a probing client nothing', function () {
    $this->getJson('/api/stations/999999/now-playing')->assertNoContent();
});

it('throttles past 20 requests per minute', function () {
    $radio = Radio::factory()->create();
    Cache::put(ReadNowPlaying::cacheKey($radio->id), ['status' => 'ok', 'title' => null], now()->addSeconds(20));

    for ($i = 0; $i < 20; $i++) {
        $this->getJson("/api/stations/{$radio->id}/now-playing")->assertOk();
    }

    $this->getJson("/api/stations/{$radio->id}/now-playing")->assertStatus(429);
});

it("lets one listener's read populate the cache the way a later poll observes it", function () {
    $radio = Radio::factory()->create(['url' => 'https://example.test/stream']);
    Http::fake(['*' => Http::response(nowPlayingIcyBody(8, 'Artist - Track'), 200, ['icy-metaint' => '8'])]);

    // Run the job directly (not ->afterResponse()) so its effect is
    // observable synchronously, rather than depending on the test client
    // invoking the kernel's terminate() callback.
    ReadNowPlaying::dispatch($radio->id);

    expect(Cache::get(ReadNowPlaying::cacheKey($radio->id)))
        ->toBe(['status' => 'ok', 'title' => 'Artist - Track']);

    $this->getJson("/api/stations/{$radio->id}/now-playing")
        ->assertExactJson(['title' => 'Artist - Track', 'state' => 'playing', 'poll_after' => 15]);
});

it("discards a StreamTitle that is just the station's own name padded in, per §6.5 step 4", function () {
    $radio = Radio::factory()->create(['url' => 'https://example.test/stream', 'title' => 'Test FM']);
    Http::fake(['*' => Http::response(nowPlayingIcyBody(8, 'Test FM'), 200, ['icy-metaint' => '8'])]);

    ReadNowPlaying::dispatch($radio->id);

    expect(Cache::get(ReadNowPlaying::cacheKey($radio->id)))
        ->toBe(['status' => 'ok', 'title' => null]);
});

it('dispatches the job after the response is sent, so a cache-miss request never runs the read itself', function () {
    $radio = Radio::factory()->create(['url' => 'https://example.test/stream']);
    Http::fake(['*' => Http::response(nowPlayingIcyBody(8, 'Artist - Track'), 200, ['icy-metaint' => '8'])]);

    // The Pest/Laravel test client calls the kernel's terminate() after
    // every request, which is exactly where ->afterResponse() runs its
    // job — so by the time this call returns, the deferred read has
    // already happened, and a second request sees it cached.
    $this->getJson("/api/stations/{$radio->id}/now-playing")
        ->assertExactJson(['title' => null, 'state' => 'connecting', 'poll_after' => 15]);

    expect(Cache::get(ReadNowPlaying::cacheKey($radio->id)))
        ->toBe(['status' => 'ok', 'title' => 'Artist - Track']);
});

it('lets ten listeners of the same station produce at most one upstream read', function () {
    $radio = Radio::factory()->create(['url' => 'https://example.test/stream']);
    Http::fake(['*' => Http::response(nowPlayingIcyBody(8, 'Artist - Track'), 200, ['icy-metaint' => '8'])]);

    // Simulate "another worker is already mid-read for this station" by
    // holding the exact lock ShouldBeUnique itself would acquire.
    $lock = Cache::lock(UniqueLock::getKey(new ReadNowPlaying($radio->id)), 20);
    expect($lock->get())->toBeTrue();

    ReadNowPlaying::dispatch($radio->id);

    Http::assertNothingSent();
    expect(Cache::get(ReadNowPlaying::cacheKey($radio->id)))->toBeNull();

    $lock->release();

    ReadNowPlaying::dispatch($radio->id);

    Http::assertSentCount(1);
    expect(Cache::get(ReadNowPlaying::cacheKey($radio->id))['title'])->toBe('Artist - Track');
});
