<?php

namespace App\Http\Controllers;

use App\Models\Radio;
use Illuminate\Http\Response;

/**
 * Public, unauthenticated playback-outcome telemetry. Advisory only:
 * `local_ok` may demote a station in ordering, but this never deletes one,
 * never hides one by default, and never touches `is_alive` — that verdict
 * belongs to Radio Browser alone.
 *
 * Both actions return 204 always, including for an unknown id (handled by
 * the route's `missing()` binding in routes/web.php) — this is telemetry,
 * and a probing client should learn nothing from the response.
 */
class StationHealthController extends Controller
{
    public function unreachable(Radio $radio): Response
    {
        $radio->forceFill([
            'local_ok' => false,
            'local_checked_at' => now(),
        ])->save();

        return response()->noContent();
    }

    public function ok(Radio $radio): Response
    {
        $radio->forceFill([
            'local_ok' => true,
            'local_checked_at' => now(),
        ])->save();

        return response()->noContent();
    }
}
