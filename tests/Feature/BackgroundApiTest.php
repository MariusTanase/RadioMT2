<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.unsplash.access_key', 'test-key');
    cache()->flush();
});

it('returns the photo url from unsplash', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=mountain')
        ->assertOk()
        ->assertExactJson(['url' => 'https://images.unsplash.test/photo.jpg']);
});

it('sends the category and the client id to unsplash', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=forest');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'query=forest')
        && str_contains($request->url(), 'orientation=landscape')
        && $request->hasHeader('Authorization', 'Client-ID test-key'));
});

it('falls back to the local image when unsplash fails', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(status: 503)]);

    $this->getJson('/api/background?query=beach')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);
});

it('falls back to the local image when the response has no photo url', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(['urls' => []])]);

    $this->getJson('/api/background?query=sky')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);
});

it('falls back to the local image when unsplash is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->getJson('/api/background?query=cozy')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);
});

it('never calls unsplash when no api key is configured', function () {
    config()->set('services.unsplash.access_key', null);
    Http::fake();

    $this->getJson('/api/background?query=japan')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);

    Http::assertNothingSent();
});

it('rejects a category the ui does not offer', function () {
    Http::fake();

    $this->getJson('/api/background?query=../../etc/passwd')->assertStatus(422);

    Http::assertNothingSent();
});

it('defaults to the mountain category', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background')->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'query=mountain'));
});

it('caches a successful lookup instead of calling unsplash twice', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=cat');
    $this->getJson('/api/background?query=cat');

    Http::assertSentCount(1);
});

it('does not cache a fallback', function () {
    // Note: a single Http::fake() call with a response sequence is used here
    // instead of two separate Http::fake() calls for the same URL pattern.
    // In this Laravel version, the first stub registered for a matching
    // pattern always wins for subsequent calls within the same test — a
    // second Http::fake() call for the same wildcard is a no-op (verified
    // directly against Illuminate\Http\Client\Factory::fake()/stubUrl(),
    // which merge/append stub callbacks rather than replace them). A
    // sequence is the correct way to return different responses across
    // successive calls to the same endpoint within one test.
    Http::fake([
        'api.unsplash.com/*' => Http::sequence()
            ->push(status: 500)
            ->push(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=dog');

    $this->getJson('/api/background?query=dog')
        ->assertExactJson(['url' => 'https://images.unsplash.test/photo.jpg']);
});
