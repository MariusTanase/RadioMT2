{{--
    The Tuning Deck — the now-playing readout (design direction §7, §4.2).

    Bound to the `radioPlayer` Alpine component declared on <body>. `current` exposes
    only { id, title, genre, image, url } — there is no country/bitrate/codec field on
    the station object, so the Deck renders a single metadata cell (genre) and omits the
    country/stream cells the design direction sketches; per §4.2 "a cell with no data is
    omitted, never rendered as an em-dash placeholder", which is exactly this case.

    Dead-stream detection (§6.5) isn't part of the given Alpine contract (no error/failed
    flag is exposed), so it is reconstructed locally here from the audio element's real
    events: a capture-phase listener on window catches `error`/`playing` (both non-bubbling)
    regardless of where the shared <audio> element lives, guarded to the AUDIO tag so a
    broken artwork <img> next door can never falsely trip it. An 8s timer arms whenever
    `current` changes and flips the status line on if `isPlaying` still isn't true by then.
--}}
<section aria-label="Now playing"
         class="bg-menu text-app-text mx-auto mt-8 w-full max-w-[1100px] rounded-t-2xl rounded-b-[2rem] px-8 pt-8 pb-6 max-[720px]:mt-4 max-[720px]:px-4 max-[720px]:pt-5 max-[720px]:pb-4"
         x-show="!uiHidden"
         x-data="{
             streamFailed: false,
             failTimer: null,
             settle: true,
             resetFailTimer() {
                 clearTimeout(this.failTimer);
                 this.streamFailed = false;
                 this.failTimer = setTimeout(() => {
                     if (current && !isPlaying) { this.streamFailed = true; }
                 }, 8000);
             },
         }"
         x-init="
             resetFailTimer();
             $watch('current', () => {
                 resetFailTimer();
                 settle = false;
                 $nextTick(() => settle = true);
             });
         "
         x-on:playing.window.capture="if ($event.target.tagName === 'AUDIO') { streamFailed = false; clearTimeout(failTimer); }"
         x-on:error.window.capture="if ($event.target.tagName === 'AUDIO') { streamFailed = true; clearTimeout(failTimer); }">

    <div class="flex flex-wrap items-center justify-between gap-8 max-[720px]:flex-col max-[720px]:items-stretch max-[720px]:gap-5">

        {{-- Left cluster: artwork + station identity --}}
        <div class="flex min-w-0 items-center gap-6 max-[720px]:flex-col max-[720px]:text-center">

            {{-- Artwork circle. Monogram sits underneath; a missing or failing <img> just
                 reveals it via onerror="this.remove()" — never a broken-image icon, never a flash. --}}
            <div class="border-app-text bg-menu-hover relative h-[120px] w-[120px] shrink-0 overflow-hidden rounded-full border max-[720px]:h-[88px] max-[720px]:w-[88px]">
                <template x-if="current">
                    <span class="font-sans text-player absolute inset-0 flex items-center justify-center text-[40px] font-light transition-opacity duration-200 motion-reduce:duration-0 max-[720px]:text-[28px]"
                          aria-hidden="true"
                          x-bind:class="settle ? 'opacity-100' : 'opacity-0'"
                          x-text="(current.title.replace(/[^\p{L}\p{N}]/gu, '') || '?').charAt(0).toUpperCase()"></span>
                </template>
                <template x-if="current?.image">
                    <img class="absolute inset-0 h-full w-full object-cover transition-opacity duration-200 motion-reduce:duration-0"
                         x-bind:class="settle ? 'opacity-100' : 'opacity-0'"
                         x-bind:src="current.image"
                         x-bind:alt="`Artwork for ${current.title}`"
                         loading="lazy"
                         onerror="this.remove()">
                </template>
            </div>

            {{-- Name + metadata + status --}}
            <div class="min-w-0">
                <template x-if="!current">
                    <p class="font-sans text-player m-0 text-[40px] leading-[1.1] font-light opacity-55 max-[720px]:text-[28px]">
                        Pick a station
                    </p>
                </template>

                <template x-if="current">
                    <h2 class="font-sans text-player m-0 line-clamp-2 text-[40px] leading-[1.1] font-light tracking-[-0.01em] break-words [overflow-wrap:anywhere] transition-opacity duration-200 motion-reduce:duration-0 max-[720px]:text-[28px]"
                        x-bind:class="settle ? 'opacity-100' : 'opacity-0'"
                        x-bind:title="current.title"
                        x-text="current.title"></h2>
                </template>

                <template x-if="current?.genre">
                    <div class="mt-4 flex flex-wrap justify-start gap-2 max-[720px]:justify-center">
                        <span class="font-sans border-[color:color-mix(in_srgb,var(--color-app-text)_18%,transparent)] rounded-[4px] border px-2 py-[3px] text-[12px] font-medium [font-variant-numeric:tabular-nums]"
                              x-text="current.genre"></span>
                    </div>
                </template>

                <template x-if="streamFailed">
                    <p class="font-opensans mt-3 text-[13px] opacity-80">
                        This stream didn't answer.
                        <button type="button"
                                class="text-app-text underline underline-offset-2 hover:text-icon-hover focus-visible:[outline:2px_solid_var(--color-icon-hover)] focus-visible:outline-offset-2"
                                @click="next()">Try the next one</button>
                    </p>
                </template>
            </div>
        </div>

        {{-- Right cluster: transport + shuffle/volume --}}
        <div class="flex shrink-0 flex-col items-end gap-4 max-[720px]:w-full max-[720px]:items-stretch">
            <div class="flex items-center gap-7 max-[720px]:justify-evenly">
                <button type="button"
                        class="text-app-text hover:text-icon-hover cursor-pointer border-none bg-transparent text-[2.25rem] font-light transition-colors duration-150 ease-out focus-visible:[outline:2px_solid_var(--color-icon-hover)] focus-visible:outline-offset-2"
                        @click="previous()" aria-label="Previous station">
                    <x-icon.backward />
                </button>

                <button type="button"
                        class="text-app-text hover:text-icon-hover cursor-pointer border-none bg-transparent text-[2.25rem] font-light transition-colors duration-150 ease-out focus-visible:[outline:2px_solid_var(--color-icon-hover)] focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-40"
                        x-bind:disabled="!current"
                        x-bind:aria-disabled="!current ? 'true' : 'false'"
                        @click="current && toggle()"
                        x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                    <template x-if="isPlaying"><x-icon.pause /></template>
                    <template x-if="!isPlaying"><x-icon.play /></template>
                </button>

                <button type="button"
                        class="text-app-text hover:text-icon-hover cursor-pointer border-none bg-transparent text-[2.25rem] font-light transition-colors duration-150 ease-out focus-visible:[outline:2px_solid_var(--color-icon-hover)] focus-visible:outline-offset-2"
                        @click="next()" aria-label="Next station">
                    <x-icon.forward />
                </button>
            </div>

            <div class="flex items-center gap-4 max-[720px]:justify-center">
                <button type="button"
                        class="text-app-text hover:text-icon-hover cursor-pointer border-none bg-transparent text-[1.1rem] font-light transition-colors duration-150 ease-out focus-visible:[outline:2px_solid_var(--color-icon-hover)] focus-visible:outline-offset-2"
                        @click="shuffle()" aria-label="Shuffle station">
                    <x-icon.shuffle />
                </button>

                <input type="range" min="0" max="1" step="0.01" class="volume-slider w-[160px]"
                       aria-label="Volume"
                       x-model.number="volume"
                       @input="setVolume($event.target.value)">
            </div>
        </div>
    </div>
</section>
