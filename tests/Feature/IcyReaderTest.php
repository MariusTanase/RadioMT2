<?php

use App\Support\IcyReader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Builds a synthetic ICY byte stream: `$audioBlocks` intervals of
 * `$metaInt` bytes of "audio", each followed by one metadata block. A block
 * for a null title is the single zero length-byte (an empty interval, per
 * §6.5 — "the first block is very often empty"); a string title is encoded
 * as `StreamTitle='...';`, padded to a multiple of 16 bytes.
 *
 * @param  list<string|null>  $titles  one entry per interval
 */
function icyBody(int $metaInt, array $titles): string
{
    $body = '';

    foreach ($titles as $title) {
        $body .= str_repeat('a', $metaInt); // audio filler, discarded by the reader

        if ($title === null) {
            $body .= chr(0); // empty metadata block

            continue;
        }

        $meta = "StreamTitle='{$title}';";
        $padded = str_pad($meta, (int) (ceil(strlen($meta) / 16) * 16), "\0");
        $body .= chr((int) (strlen($padded) / 16)).$padded;
    }

    return $body;
}

it('parses a title out of a synthetic ICY byte stream, skipping empty intervals first', function () {
    Http::fake([
        '*' => Http::response(
            icyBody(8, [null, 'Artist - Track']),
            200,
            ['icy-metaint' => '8', 'icy-name' => 'Test Radio', 'icy-genre' => 'Pop', 'icy-br' => '128']
        ),
    ]);

    $result = (new IcyReader)->read('https://example.test/stream');

    expect($result->status)->toBe('ok')
        ->and($result->title)->toBe('Artist - Track')
        ->and($result->headers)->toBe([
            'icy-name' => 'Test Radio',
            'icy-genre' => 'Pop',
            'icy-br' => '128',
        ]);
});

it('returns null without throwing for a stream with no icy-metaint header', function () {
    Http::fake([
        '*' => Http::response('mp3 bytes with no metadata channel at all', 200, ['icy-name' => 'Talk Radio']),
    ]);

    $result = (new IcyReader)->read('https://example.test/talk-stream');

    expect($result->status)->toBe('unsupported')
        ->and($result->title)->toBeNull();
});

it('returns unreachable without throwing when the connection fails', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not connect');
    });

    $result = (new IcyReader)->read('https://example.test/dead-stream');

    expect($result->status)->toBe('unreachable')
        ->and($result->title)->toBeNull();
});

it('refuses a non-http(s) url without making any request', function () {
    Http::fake();

    $result = (new IcyReader)->read('file:///etc/passwd');

    expect($result->status)->toBe('unreachable')
        ->and($result->title)->toBeNull();

    Http::assertNothingSent();
});

it('stops within its byte cap on a stream that never sends a non-empty title', function () {
    // 20 empty intervals is far more than a 64KB-budget reader should ever
    // need to traverse — this proves the loop terminates rather than
    // spinning until the fake body (which Http::fake keeps in memory
    // in full) runs out.
    Http::fake([
        '*' => Http::response(icyBody(1000, array_fill(0, 20, null)), 200, ['icy-metaint' => '1000']),
    ]);

    $result = (new IcyReader(maxBytes: 8000))->read('https://example.test/quiet-stream');

    expect($result->status)->toBe('ok')
        ->and($result->title)->toBeNull();
});
