<?php

namespace App\Support;

/**
 * The outcome of one IcyReader::read() call. `status` distinguishes three
 * cases that all render as "no title" to a caller that only looks at
 * `title`, but need different cache TTLs and polling backoff (§6.5 of the
 * redesign direction):
 *
 * - `ok`           the stream speaks ICY; `title` is the current
 *                   StreamTitle, or null if this interval's block was empty.
 * - `unsupported`  the stream answered but sent no `icy-metaint` header —
 *                   it will never send a title (talk/news stations).
 * - `unreachable`  the connection failed, refused, or timed out.
 */
final class IcyReadResult
{
    private function __construct(
        public readonly ?string $title,
        public readonly array $headers,
        public readonly string $status,
    ) {}

    /** @param array<string, string|null> $headers */
    public static function ok(?string $title, array $headers): self
    {
        return new self($title, $headers, 'ok');
    }

    /** @param array<string, string|null> $headers */
    public static function unsupported(array $headers = []): self
    {
        return new self(null, $headers, 'unsupported');
    }

    public static function unreachable(): self
    {
        return new self(null, [], 'unreachable');
    }

    /** @return array{title: ?string, headers: array, status: string} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'headers' => $this->headers,
            'status' => $this->status,
        ];
    }
}
