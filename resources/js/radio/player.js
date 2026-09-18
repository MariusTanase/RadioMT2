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

        boot() {
            if (this.stations.length === 0) {
                return;
            }

            // Pick the station before the element ever gets a src, so nothing
            // hardcoded autoplays and is then replaced by the random pick.
            this.index = Math.floor(Math.random() * this.stations.length);
            this.setVolume(this.volume);
            this.play();
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

        select(id) {
            const index = this.stations.findIndex((station) => station.id === id);

            if (index !== -1) {
                this.index = index;
                this.play();
            }
        },

        next() {
            this.index = this.index === this.stations.length - 1 ? 0 : this.index + 1;
            this.play();
        },

        previous() {
            this.index = this.index === 0 ? this.stations.length - 1 : this.index - 1;
            this.play();
        },

        shuffle() {
            this.index = Math.floor(Math.random() * this.stations.length);
            this.play();
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
