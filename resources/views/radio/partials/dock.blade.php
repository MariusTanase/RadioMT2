{{--
    The dock. Fixed, outside the swappable region, so browsing never interrupts
    playback. Three cells: what is on, the transport, and volume. It carries the
    live track title from the ICY poller when the station sends one — most music
    stations do, no talk station does, so the layout must not reserve space for it.
--}}
<div class="dock">
    <div class="dock-in">

        <div class="flex items-center gap-3" style="min-width:0">
            <template x-if="current">
                <span class="dock-art">
                    <span x-text="(current.title || '?').replace(/[^\p{L}\p{N}]/gu,'').charAt(0).toUpperCase() || '?'"></span>
                    <template x-if="current.image">
                        <img x-bind:src="current.image" alt="" onerror="this.remove()">
                    </template>
                </span>
            </template>

            <span style="min-width:0">
                <template x-if="!current">
                    <span class="dock-idle">Pick a station</span>
                </template>
                <template x-if="current">
                    <span style="display:block;min-width:0">
                        <span class="dock-name truncate1" style="display:block" x-text="current.title"></span>
                        <span class="dock-track truncate1" style="display:block"
                              x-text="$store.nowPlaying?.title || ''"
                              x-show="$store.nowPlaying?.title"></span>
                    </span>
                </template>
            </span>
        </div>

        <div class="flex items-center gap-1">
            <button class="tbtn" @click="previous()" aria-label="Previous station"><x-icon.backward /></button>
            <button class="pbtn" x-bind:disabled="!current" @click="current && toggle()"
                    x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                <template x-if="isPlaying"><x-icon.pause /></template>
                <template x-if="!isPlaying"><x-icon.play /></template>
            </button>
            <button class="tbtn" @click="next()" aria-label="Next station"><x-icon.forward /></button>
        </div>

        <div class="flex items-center justify-end gap-3 max-[720px]:hidden">
            <button class="tbtn" @click="shuffle()" aria-label="Play something random"><x-icon.shuffle /></button>
            <input class="vol" type="range" min="0" max="1" step="0.01" aria-label="Volume"
                   x-model.number="volume" @input="setVolume($event.target.value)">
        </div>

        <button class="tbtn hidden max-[720px]:grid" @click="shuffle()" aria-label="Play something random">
            <x-icon.shuffle />
        </button>
    </div>
</div>
