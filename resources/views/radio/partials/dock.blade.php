{{--
    The dock. Fixed and outside the swappable region, so browsing never
    interrupts playback.

    The spectrum sits behind the readout rather than beside it — the station
    name reads over its own sound. It is synthesised, not analysed: feeding a
    Web Audio analyser needs crossOrigin on the media element, which silences
    every stream whose server omits CORS headers, and most of these do.
--}}
<div class="dock">

    <canvas class="dock-spectrum" aria-hidden="true"></canvas>

    <div class="dock-in">

        {{-- what's on --}}
        <div class="dock-now">
            <span class="dock-art" x-bind:class="isPlaying ? 'is-live' : ''">
                <template x-if="current">
                    <span x-text="(current.title || '?').replace(/[^\p{L}\p{N}]/gu,'').charAt(0).toUpperCase() || '?'"></span>
                </template>
                <template x-if="current && current.image">
                    <img x-bind:src="current.image" alt="" onerror="this.remove()">
                </template>
            </span>

            <span class="dock-text">
                <template x-if="!current">
                    <span class="dock-idle">Nothing playing — pick a station</span>
                </template>
                <template x-if="current">
                    <span style="display:block;min-width:0">
                        <span class="dock-name truncate1" x-text="current.title"></span>
                        <span class="dock-track truncate1"
                              x-show="$store.nowPlaying?.title"
                              x-text="$store.nowPlaying?.title || ''"></span>
                        <span class="dock-track truncate1 dock-dim"
                              x-show="!$store.nowPlaying?.title"
                              x-text="current.genre || ''"></span>
                    </span>
                </template>
            </span>
        </div>

        {{-- transport --}}
        <div class="dock-transport">
            <button class="tbtn" @click="previous()" aria-label="Previous station"><x-icon.backward /></button>
            <button class="pbtn" x-bind:disabled="!current" @click="current && toggle()"
                    x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                <template x-if="isPlaying"><x-icon.pause /></template>
                <template x-if="!isPlaying"><x-icon.play /></template>
            </button>
            <button class="tbtn" @click="next()" aria-label="Next station"><x-icon.forward /></button>
        </div>

        {{-- shuffle + volume --}}
        <div class="dock-aux">
            <button class="tbtn" @click="shuffle()" aria-label="Play something random"><x-icon.shuffle /></button>
            <input class="vol" type="range" min="0" max="1" step="0.01" aria-label="Volume"
                   x-model.number="volume" @input="setVolume($event.target.value)">
        </div>
    </div>
</div>
