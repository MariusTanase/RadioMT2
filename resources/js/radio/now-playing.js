// The now-playing poller (§6.5 of the redesign direction). Browsers cannot
// read a stream's in-band ICY metadata from an <audio> element, so the
// track title has to come from our own server, which reads it once per
// station (App\Jobs\ReadNowPlaying) and caches it. This file is the client
// half: it polls GET /api/stations/{id}/now-playing for whichever ONE
// station is currently playing, and nothing else.
//
// This module is deliberately self-contained: it doesn't import or assume
// anything about the current player/dock implementation, since that markup
// and its controller (player.js) belong to other work in flight. It
// registers `Alpine.store('nowPlaying', ...)` itself on `alpine:init`, and
// exposes a small public surface — `start(stationId)` / `stop()` plus the
// reactive `title` / `state` — for whatever wires up the dock to call.
//
// It also self-observes the page's actual playback without needing any
// cooperation: `play`/`pause`/`ended`/`error` on an <audio> element do NOT
// bubble, but capturing-phase listeners on `document` still see them (Media
// events are dispatched down through every ancestor during the capture
// phase regardless of `bubbles`). So `init()` below wires those up as a
// fallback path — call `start(id)`/`stop()` explicitly if the player has a
// more precise moment (e.g. the `playing` event, which is closer to the
// dock's own "Connecting" -> "Playing" transition than the element's `play`).
document.addEventListener('alpine:init', () => {
    Alpine.store('nowPlaying', {
        // ---- reactive state the dock binds to -----------------------------
        title: null,
        state: 'idle', // idle | connecting | playing | failed (never sets 'rebuffering' — that's audio-buffering, a client-only concept)

        // ---- private bookkeeping -------------------------------------------
        _stationId: null,
        _timer: null,
        _controller: null,
        _visibilityBound: false,

        // Call when a station starts playing. Safe to call again for the
        // same id (a no-op re-arm) or a different one (retunes cleanly).
        start(stationId) {
            if (!stationId) {
                return;
            }

            if (this._stationId === stationId && this.state !== 'idle') {
                return; // already tracking this station
            }

            this._reset(stationId);
            this._bindVisibility();

            // First poll 2s after playback starts (§6.5) — an ICY title
            // rarely exists before the stream has even buffered.
            this._schedule(2000);
        },

        // Call when playback stops for any reason (pause, station change,
        // failure). Cancels everything in flight and returns to idle.
        stop() {
            this._controller?.abort();
            this._controller = null;
            clearTimeout(this._timer);
            this._timer = null;
            this._stationId = null;
            this.title = null;
            this.state = 'idle';
        },

        // Alpine calls a store's `init()` automatically as soon as it's
        // registered, so this wiring is live with no changes needed
        // anywhere else. Best-effort auto-wiring: looks for a station id on
        // the <audio> element (or an ancestor) via `data-station-id`; if
        // the current markup doesn't carry one yet, this simply never
        // fires and `start`/`stop` remain the supported integration point.
        init() {
            document.addEventListener('playing', (event) => this._onAudioPlaying(event), true);
            document.addEventListener('pause', (event) => this._onAudioPause(event), true);
            document.addEventListener('ended', (event) => this._onAudioPause(event), true);
            document.addEventListener('error', (event) => this._onAudioPause(event), true);
        },

        _onAudioPlaying(event) {
            const id = this._stationIdFrom(event.target);

            if (id) {
                this.start(id);
            }
        },

        _onAudioPause(event) {
            const id = this._stationIdFrom(event.target);

            if (id && id === this._stationId) {
                this.stop();
            }
        },

        _stationIdFrom(el) {
            const carrier = el?.closest?.('[data-station-id]') ?? el;

            return carrier?.dataset?.stationId ?? null;
        },

        _reset(stationId) {
            this._controller?.abort();
            this._controller = null;
            clearTimeout(this._timer);
            this._timer = null;
            this._stationId = stationId;
            this.title = null;
            this.state = 'connecting';
        },

        _bindVisibility() {
            if (this._visibilityBound) {
                return;
            }

            this._visibilityBound = true;

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    // Stop polling entirely while backgrounded — no timers,
                    // no in-flight requests left running against a tab
                    // nobody is looking at.
                    clearTimeout(this._timer);
                    this._timer = null;
                    this._controller?.abort();
                    this._controller = null;
                } else if (this._stationId) {
                    // Resume with an immediate poll on return.
                    this._poll();
                }
            });
        },

        _schedule(delayMs) {
            clearTimeout(this._timer);

            if (document.visibilityState !== 'visible') {
                // Don't even arm a timer while hidden; visibilitychange
                // above will poll immediately once the tab is visible again.
                return;
            }

            this._timer = setTimeout(() => this._poll(), delayMs);
        },

        _poll() {
            if (!this._stationId || document.visibilityState !== 'visible') {
                return;
            }

            const stationId = this._stationId;

            this._controller?.abort();
            this._controller = new AbortController();

            fetch(`/api/stations/${stationId}/now-playing`, {
                signal: this._controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((data) => {
                    // The station changed (or playback stopped) while this
                    // request was in flight — discard, never let a stale
                    // response overwrite a newer station's state.
                    if (!data || this._stationId !== stationId) {
                        return;
                    }

                    this.title = data.title ?? null;
                    this.state = data.state ?? this.state;

                    this._schedule(Math.max(1, Number(data.poll_after) || 15) * 1000);
                })
                .catch(() => {
                    // A network failure leaves the last known title alone
                    // and never breaks playback — just try again on the
                    // usual cadence.
                    if (this._stationId === stationId) {
                        this._schedule(15000);
                    }
                });
        },
    });
});
