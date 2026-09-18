<?php

namespace App\Jobs;

use App\Models\Radio;
use App\Support\IcyReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One upstream ICY read per station, no matter how many listeners are
 * currently tuned to it (§6.5 of the redesign direction). `ShouldBeUnique`
 * is the mechanism: the framework only lets this job actually run while it
 * can hold `Cache::lock("laravel_unique_job:...:{$radioId}")`, so ten
 * listeners polling the same station at once produce at most one read.
 *
 * `$uniqueFor` is deliberately short (20s, matching the "ok" cache TTL
 * below) — if a read somehow gets stuck against a slow/dead stream, the
 * station is never locked out of future reads for longer than one polling
 * window. `IcyReader`'s own wall-clock budget is the real backstop; this is
 * just a second, cheap one.
 */
class ReadNowPlaying implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 20;

    public function __construct(public readonly int $radioId) {}

    public function uniqueId(): string
    {
        return (string) $this->radioId;
    }

    public function handle(IcyReader $reader): void
    {
        $radio = Radio::find($this->radioId);

        if (! $radio || ! is_string($radio->url) || $radio->url === '') {
            return;
        }

        $result = $reader->read($radio->url);
        $key = self::cacheKey($this->radioId);

        match ($result->status) {
            'unreachable' => Cache::put($key, ['status' => 'unreachable', 'title' => null], now()->addMinutes(5)),
            'unsupported' => Cache::put($key, ['status' => 'unsupported', 'title' => null], now()->addMinutes(30)),
            default => Cache::put($key, [
                'status' => 'ok',
                'title' => $this->meaningfulTitle($result->title, $radio, $result->headers),
            ], now()->addSeconds(20)),
        };
    }

    /**
     * Many stations pad `StreamTitle` with their own name rather than
     * leaving it empty when nothing is playing — that is not a song, so it
     * is discarded rather than cached as one (§6.5 step 4).
     *
     * @param  array<string, string|null>  $headers
     */
    private function meaningfulTitle(?string $title, Radio $radio, array $headers): ?string
    {
        if ($title === null || $title === '') {
            return null;
        }

        $noise = array_filter([$radio->title, $headers['icy-name'] ?? null], fn ($v) => filled($v));

        foreach ($noise as $value) {
            if (Str::lower($title) === Str::lower($value)) {
                return null;
            }
        }

        return $title;
    }

    public static function cacheKey(int $radioId): string
    {
        return "nowplaying:{$radioId}";
    }
}
