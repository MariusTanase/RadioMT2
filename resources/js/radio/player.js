export default function radioPlayer() {
    return {
        stations: window.RADIO_STATIONS ?? [],
        index: 0,
        isPlaying: false,
        volume: 0.1,
        uiHidden: false,

        get current() {
            return this.stations[this.index] ?? null;
        },

        get audio() {
            return this.$refs.audio;
        },

        // Filtering or paging swaps #catalogue without reloading the document, so
        // the player must adopt the new page's stations while whatever is playing
        // keeps playing — even when that station is no longer in the result set.
        adoptPage(stations) {
            if (!Array.isArray(stations)) {
                return;
            }

            const playing = this.current;

            this.stations = stations;

            if (!playing) {
                this.index = 0;

                return;
            }

            const stillListed = stations.findIndex((s) => s.id === playing.id);

            // Not on this page any more: keep it at the end so `current` still
            // resolves to it and the audio element is never re-pointed.
            this.index = stillListed === -1
                ? this.stations.push(playing) - 1
                : stillListed;
        },

        boot() {
            if (this.stations.length === 0) {
                return;
            }

            // Pick the station before the element ever gets a src, so nothing
            // hardcoded autoplays and is then replaced by the random pick.
            this.index = Math.floor(Math.random() * this.stations.length);
            this.setVolume(this.volume);
            this.$nextTick(() => this.play());

            window.addEventListener('catalogue:swapped', (event) => {
                this.adoptPage(event.detail?.stations);
            });
        },

        // Space toggles playback, but only when focus isn't inside a form
        // control (the volume slider, transport buttons, …) — otherwise
        // Space would both toggle the player and actuate whatever has
        // focus, and preventDefault() would swallow legitimate input.
        onKeydown(event) {
            if (event.code !== 'Space') {
                return;
            }

            if (event.target.closest('input, button, select, textarea, [contenteditable]')) {
                return;
            }

            event.preventDefault();
            this.toggle();
        },

        play() {
            // Let the element's own events drive isPlaying: play() rejects when
            // autoplay is blocked or the stream is unreachable, and the button
            // must not claim to be playing in that case.
            this.audio?.play().catch(() => {});
        },

        pause() {
            this.audio?.pause();
        },

        toggle() {
            this.isPlaying ? this.pause() : this.play();
        },

        // Every station change defers play() to the next tick: `index` drives
        // the <audio> src through a reactive binding, and Alpine flushes those
        // asynchronously. Calling play() synchronously races the binding and
        // replays the previous station's stream, or nothing at all.
        select(id) {
            const index = this.stations.findIndex((station) => station.id === id);

            if (index !== -1) {
                this.index = index;
                this.$nextTick(() => this.play());
            }
        },

        next() {
            this.index = this.index === this.stations.length - 1 ? 0 : this.index + 1;
            this.$nextTick(() => this.play());
        },

        previous() {
            this.index = this.index === 0 ? this.stations.length - 1 : this.index - 1;
            this.$nextTick(() => this.play());
        },

        // Shuffle must reach the WHOLE filtered set, not the page on screen.
        // `stations` holds only the current 50 rows, so picking from it would
        // mean "random among the 50 you can already see" — with House selected
        // that is 50 of 771, and with no filter 50 of ~49,000.
        // The server picks from the full tuned set instead, honouring every
        // active filter, and falls back to the local page if it cannot be reached.
        async shuffle() {
            const params = new URLSearchParams(window.location.search);
            params.delete('page');

            try {
                const response = await fetch(`/api/catalogue/random?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    this.playStation(await response.json());

                    return;
                }
            } catch {
                // Network failure: fall through to the local pick below.
            }

            if (this.stations.length > 0) {
                this.index = Math.floor(Math.random() * this.stations.length);
                this.$nextTick(() => this.play());
            }
        },

        // Play a station that may not be on the current page. Alpine renders the
        // player from `stations[index]`, so an off-page station is appended first.
        playStation(station) {
            if (!station?.url) {
                return;
            }

            const existing = this.stations.findIndex((s) => s.id === station.id);

            this.index = existing === -1
                ? this.stations.push(station) - 1
                : existing;

            this.$nextTick(() => this.play());
        },

        setVolume(value) {
            this.volume = Number(value);

            if (this.audio) {
                this.audio.volume = this.volume;
            }
        },

        toggleUi() {
            this.uiHidden = !this.uiHidden;
        },
    };
}
