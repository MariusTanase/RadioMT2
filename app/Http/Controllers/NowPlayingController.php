<?php

namespace App\Http\Controllers;

use App\Jobs\ReadNowPlaying;
use App\Models\Radio;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/stations/{radio}/now-playing — a pure cache read (§6.5 of the
 * redesign direction). This action never opens a socket to the upstream
 * stream itself: `ReadNowPlaying` does that, and is handed off via
 * `->afterResponse()` so the (possibly slow) upstream read only ever starts
 * once this response has already been handed to the client.
 *
 * That holds under any QUEUE_CONNECTION, including `sync`: Laravel's
 * `dispatchAfterResponse()` defers the job to the HTTP kernel's
 * `terminate()` callback — which Symfony's `Response::send()` calls
 * *after* `fastcgi_finish_request()` has already flushed the response to
 * the browser — rather than running it inline while this action is still
 * building its reply. The endpoint itself only ever does a `Cache::get()`.
 *
 * Unknown station ids never reach this method: the route's `missing()`
 * binding in routes/web.php answers 204 first, matching the existing
 * station-health endpoints.
 */
class NowPlayingController extends Controller
{
    public function show(Radio $radio): JsonResponse
    {
        $cached = Cache::get(ReadNowPlaying::cacheKey($radio->id));

        if ($cached === null) {
            ReadNowPlaying::dispatch($radio->id)->afterResponse();

            return response()->json([
                'title' => null,
                'state' => 'connecting',
                'poll_after' => 15,
            ]);
        }

        return response()->json(match ($cached['status'] ?? 'ok') {
            // The upstream connection failed outright.
            'unreachable' => ['title' => null, 'state' => 'failed', 'poll_after' => 120],
            // The stream is fine but will never send a title (talk/news) —
            // still "playing" as far as the dock is concerned, just polled
            // far less often.
            'unsupported' => ['title' => null, 'state' => 'playing', 'poll_after' => 60],
            default => ['title' => $cached['title'], 'state' => 'playing', 'poll_after' => 15],
        });
    }
}
