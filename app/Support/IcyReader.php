<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Reads a station's live stream just long enough to learn its current ICY
 * `StreamTitle`, then disconnects. This is the only piece of code in the
 * app that talks to an upstream radio stream directly — everything else
 * (the endpoint, the frontend) only ever reads what this wrote to cache.
 *
 * Mechanism (verified against real streams — §6.5 of the redesign
 * direction): request with `Icy-MetaData: 1`, read `icy-metaint` from the
 * response headers, read that many audio bytes (discarded), read one length
 * byte (× 16 = metadata block size), read that many bytes and look for
 * `StreamTitle='...';` inside them. The first block is very often empty —
 * a title is only resent when the track changes — so several intervals may
 * need reading before one turns up.
 *
 * Bounded on two independent axes so a silent, slow, or chatty stream can
 * never hang a worker: a wall-clock deadline and a total byte cap, checked
 * on every loop iteration regardless of what the stream is doing.
 *
 * Never throws. A dead connection, a non-ICY stream, and a malformed
 * response are all expected, ordinary outcomes here, not error conditions —
 * the caller gets an `IcyReadResult` back in every case.
 */
class IcyReader
{
    public function __construct(
        private readonly int $connectTimeoutSeconds = 3,
        private readonly int $totalTimeoutSeconds = 6,
        private readonly int $maxBytes = 65536,
    ) {}

    public function read(string $url): IcyReadResult
    {
        if (! $this->isHttpUrl($url)) {
            return IcyReadResult::unreachable();
        }

        try {
            $response = Http::withHeaders(['Icy-MetaData' => '1'])
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->totalTimeoutSeconds)
                ->withOptions([
                    'stream' => true,
                    // Never let a redirect hop us into a non-http(s) scheme
                    // (e.g. a crafted `file://` or `gopher://` target).
                    'allow_redirects' => ['protocols' => ['http', 'https']],
                ])
                ->get($url);
        } catch (Throwable) {
            return IcyReadResult::unreachable();
        }

        if (! $response->successful()) {
            return IcyReadResult::unreachable();
        }

        $headers = $this->stationHeaders($response);

        $metaInt = (int) $response->header('icy-metaint');

        if ($metaInt <= 0) {
            // The stream answered but will never send in-band metadata —
            // that's what stops talk/news stations being polled forever.
            $this->closeQuietly($response->toPsrResponse()->getBody());

            return IcyReadResult::unsupported($headers);
        }

        $title = $this->scanForTitle($response->toPsrResponse()->getBody(), $metaInt);

        return IcyReadResult::ok($title, $headers);
    }

    /** @return array<string, string|null> */
    private function stationHeaders($response): array
    {
        return [
            'icy-name' => $response->header('icy-name') ?: null,
            'icy-genre' => $response->header('icy-genre') ?: null,
            'icy-br' => $response->header('icy-br') ?: null,
        ];
    }

    private function scanForTitle(StreamInterface $stream, int $metaInt): ?string
    {
        $deadline = microtime(true) + $this->totalTimeoutSeconds;
        $budget = $this->maxBytes;
        $title = null;

        try {
            while ($budget > 0 && microtime(true) < $deadline && ! $stream->eof()) {
                $audio = $this->readUpTo($stream, min($metaInt, $budget));
                $budget -= strlen($audio);

                if ($stream->eof() || $budget <= 0) {
                    break;
                }

                $lengthByte = $this->readUpTo($stream, 1);
                $budget -= strlen($lengthByte);

                if ($lengthByte === '') {
                    break;
                }

                $blockLength = ord($lengthByte) * 16;

                if ($blockLength === 0) {
                    // Empty block: no track change this interval. Keep
                    // listening — a title is only resent when it changes.
                    continue;
                }

                $block = $this->readUpTo($stream, min($blockLength, $budget));
                $budget -= strlen($block);

                if (preg_match("/StreamTitle='(.*?)';/s", $block, $matches) === 1) {
                    $candidate = trim(preg_replace('/\s+/', ' ', $matches[1]) ?? '');

                    if ($candidate !== '') {
                        $title = $candidate;
                        break;
                    }
                }
            }
        } finally {
            $this->closeQuietly($stream);
        }

        return $title;
    }

    /**
     * Read up to `$length` bytes, looping until that many are collected or
     * the stream stops making progress — a single `read()` call is not
     * guaranteed to return everything asked for.
     */
    private function readUpTo(StreamInterface $stream, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $buffer = '';

        while (strlen($buffer) < $length && ! $stream->eof()) {
            $chunk = $stream->read($length - strlen($buffer));

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function closeQuietly(StreamInterface $stream): void
    {
        try {
            $stream->close();
        } catch (Throwable) {
            // Best-effort cleanup only — a failure here changes nothing
            // about the result already computed.
        }
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
