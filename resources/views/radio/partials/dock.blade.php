{{--
    The footer dock — "The Night Console" design direction, §5 (position,
    material, layout, states) plus §6 (now playing), §9 (type/colour/opacity)
    and §10 (motion). Replaces the Tuning Deck panel.

    Bound to the `radioPlayer` Alpine component declared on <body> (`current`,
    `isPlaying`, `volume`, `uiHidden`, `play/pause/toggle/next/previous/shuffle`,
    `setVolume`). The <audio> element lives elsewhere in the page shell and is
    never rendered here.

    Uses the shared §9 vocabulary from resources/css/app.css: `.nc-dock-name`,
    `.nc-dock-title`, `.nc-dock-status`, `.nc-dock-monogram` for type; `.nc-op-100`
    / `.nc-op-72` / `.nc-op-48` for the opacity law; `.nc-bevel` for every
    clickable surface's lit/shadow edge and :active depress; `.nc-ember` /
    `.nc-ember-static` for the playing indicator. `--dock-h` and #catalogue's
    clearance are already wired centrally. The one thing not published there
    is the dock plate material itself (§5.2) — it needs a third per-theme
    override (glow intensity) beyond the two §9 sanctions, so it's composed
    here, scoped to #dock, deriving only from the existing eleven tokens.

    THE FIVE DOCK STATES (§6.4) are reconstructed locally from real <audio>
    events plus one timer, per the spec's explicit instruction — not from the
    `nowPlaying` store's `state` field. That field's vocabulary happens to
    match the five dock states, but it describes the *metadata poll's*
    lifecycle (a separate, concurrently-built concern), not playback, so this
    dock does not depend on it existing. Only `$store.nowPlaying?.title` is
    read, defensively, for line 2's content while Playing.

    `loadstart` fires whenever `current`'s reactive `x-bind:src` changes the
    <audio> element's source — exactly on every station change, not on a bare
    play()/pause() of the same station — so it doubles as "a new station was
    picked" without watching `current` directly. `waiting`/`stalled` only
    count as Rebuffering once a `playing` event has already fired for the
    current attempt; before that, Connecting (and its own 8s timeout) owns it.

    A sixth, unlisted case — a station is selected but paused, not connecting,
    not failed — isn't one of the official five. It renders like Playing
    (station name lit, last known title or silence in line 2) but with the
    ember hidden, since nothing is actually breathing: the ember's presence,
    not the text, is what tells Playing and paused-with-a-pick apart.

    LAYOUT: desktop (§5.3) and mobile (§5.4) regroup controls differently
    enough (not just resized) that one reflowing grid would need per-button
    grid-area juggling. Instead this renders two sibling blocks, each shown
    only in its own breakpoint via `max-[640px]:`, so exactly one is ever in
    the accessibility tree at a time.
--}}
<style>
    /* The dock plate material (§5.2) — the one fully opaque surface on the
       page. Not part of the shared .nc- vocabulary because the glow needs a
       third per-theme override (intensity only) beyond §9's two sanctioned
       ones; scoped to #dock so it stays a one-element exception. */
    #dock {
        background-color: var(--color-app);
        background-image: linear-gradient(var(--color-menu), var(--color-menu));
        border-top: 1px solid color-mix(in srgb, var(--color-app-text) 22%, transparent);
        box-shadow: 0 -1px 24px color-mix(in srgb, var(--color-btn-border) 35%, transparent);
    }
    [data-theme='light'] #dock {
        box-shadow: 0 -1px 24px color-mix(in srgb, var(--color-btn-border) 18%, transparent);
    }

    /* The split-flap flip (§6.3, §10) — the one 3D motion moment on the
       page, not published centrally since it belongs only to this line.
       Text is swapped by JS at the 90ms midpoint (see flipTitle() below);
       the animation is purely the rotation either side of that swap. */
    .dock-flip-wrap { perspective: 240px; }
    .dock-flip-inner { transform-style: preserve-3d; transform-origin: center; }
    .dock-flip-inner.is-flipping { animation: dock-flip 180ms ease-in-out; }
    @keyframes dock-flip {
        0%   { transform: rotateX(0deg);   opacity: 1; }
        49%  { transform: rotateX(-90deg); opacity: 0; }
        51%  { transform: rotateX(90deg);  opacity: 0; }
        100% { transform: rotateX(0deg);   opacity: 1; }
    }
    @media (prefers-reduced-motion: reduce) {
        .dock-flip-inner.is-flipping { animation: none; }
    }
</style>

<div id="dock"
     role="region"
     aria-label="Player"
     x-show="!uiHidden"
     x-data="{
         connecting: false,
         rebuffering: false,
         failed: false,
         hasStartedPlaying: false,
         connectTimer: null,
         stallTimer: null,
         displayTitle: null,
         flipping: false,
         titleToken: 0,
         pendingTitle: undefined,

         get dockState() {
             if (!current) return 'idle';
             if (this.failed) return 'failed';
             if (isPlaying) return this.rebuffering ? 'rebuffering' : 'playing';
             if (this.connecting) return 'connecting';
             return 'paused';
         },

         get statusText() {
             if (this.dockState === 'connecting') return 'Connecting…';
             if (this.dockState === 'rebuffering') return 'Buffering…';
             return null;
         },

         onLoadStart() {
             this.resetTitle();
             clearTimeout(this.connectTimer);
             this.connecting = true;
             this.failed = false;
             this.hasStartedPlaying = false;
             this.connectTimer = setTimeout(() => {
                 if (current && !isPlaying) { this.failed = true; this.connecting = false; }
             }, 8000);
         },

         onPlaying() {
             clearTimeout(this.connectTimer);
             clearTimeout(this.stallTimer);
             this.connecting = false;
             this.failed = false;
             this.rebuffering = false;
             this.hasStartedPlaying = true;
         },

         onStall() {
             if (!this.hasStartedPlaying) return;
             clearTimeout(this.stallTimer);
             this.stallTimer = setTimeout(() => { this.rebuffering = true; }, 2000);
         },

         onError() {
             clearTimeout(this.connectTimer);
             clearTimeout(this.stallTimer);
             this.connecting = false;
             this.rebuffering = false;
             this.failed = true;
         },

         // Monotonic token + coalescing (§6.3): a value arriving mid-flip is
         // stored as pending and applied when the in-flight flip settles;
         // never more than one flip in flight, never more than one pending.
         flipTitle(next) {
             next = next ?? null;
             if (next === this.displayTitle) return;
             if (this.flipping) { this.pendingTitle = next; return; }
             if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                 this.displayTitle = next;
                 return;
             }
             this.titleToken++;
             const token = this.titleToken;
             this.flipping = true;
             setTimeout(() => { if (this.titleToken === token) this.displayTitle = next; }, 90);
             setTimeout(() => {
                 if (this.titleToken !== token) return;
                 this.flipping = false;
                 if (this.pendingTitle !== undefined) {
                     const pending = this.pendingTitle;
                     this.pendingTitle = undefined;
                     this.flipTitle(pending);
                 }
             }, 180);
         },

         resetTitle() {
             this.titleToken++;
             this.flipping = false;
             this.pendingTitle = undefined;
             this.displayTitle = null;
         },

         monogram(title) {
             return (title ?? '').replace(/[^\p{L}\p{N}]/gu, '').charAt(0).toUpperCase() || '?';
         },
     }"
     x-init="
         if (current) { onLoadStart(); }
         $watch('$store.nowPlaying?.title', (value) => flipTitle(value));
     "
     x-on:loadstart.window.capture="if ($event.target.tagName === 'AUDIO') onLoadStart()"
     x-on:playing.window.capture="if ($event.target.tagName === 'AUDIO') onPlaying()"
     x-on:waiting.window.capture="if ($event.target.tagName === 'AUDIO') onStall()"
     x-on:stalled.window.capture="if ($event.target.tagName === 'AUDIO') onStall()"
     x-on:error.window.capture="if ($event.target.tagName === 'AUDIO') onError()"
     class="fixed inset-x-0 bottom-0 z-40 h-[84px] max-[640px]:h-[88px]">

    {{-- Desktop (§5.3), >= 641px, 84px, three-cell grid. --}}
    <div class="grid h-full grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-4 px-4 max-[640px]:hidden">

        {{-- Left cell: the readout. --}}
        <div class="flex min-w-0 items-center gap-4">
            <div class="nc-bevel relative h-11 w-11 shrink-0 overflow-hidden rounded-[4px] bg-menu-hover"
                 x-bind:class="!current ? 'nc-op-48' : ''">
                <template x-if="current">
                    <span class="nc-dock-monogram absolute inset-0 flex items-center justify-center"
                          aria-hidden="true"
                          x-text="monogram(current.title)"></span>
                </template>
                <template x-if="current?.image">
                    <img class="absolute inset-0 h-full w-full object-cover"
                         x-bind:src="current.image"
                         alt=""
                         loading="lazy"
                         onerror="this.remove()">
                </template>
            </div>

            <div class="min-w-0">
                <p class="nc-dock-name m-0 truncate"
                   x-bind:class="!current || dockState === 'failed' ? 'nc-op-72' : 'nc-op-100'"
                   x-bind:title="current?.title ?? ''"
                   x-text="current ? current.title : 'Pick a station'"></p>

                {{-- Line 2 — the reserved slot (§6.2). Always exists, often empty. --}}
                <div class="mt-0.5 flex h-[18px] items-center gap-1.5">
                    <span class="nc-ember h-1.5 w-1.5 shrink-0"
                          x-show="dockState === 'connecting' || dockState === 'rebuffering' || dockState === 'playing'"
                          x-bind:class="dockState === 'playing' ? '' : 'nc-ember-static'"
                          aria-hidden="true"></span>

                    <div aria-live="polite" class="min-w-0 flex-1">
                        <template x-if="dockState === 'connecting' || dockState === 'rebuffering'">
                            <span class="nc-dock-status block truncate" x-text="statusText"></span>
                        </template>

                        <template x-if="dockState === 'failed'">
                            <p class="nc-dock-status m-0 truncate">
                                Stream didn't answer —
                                <button type="button"
                                        class="text-app-text underline underline-offset-2 hover:text-icon-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-icon-hover"
                                        @click="next()">Try the next one ›</button>
                            </p>
                        </template>

                        <template x-if="dockState === 'playing' || dockState === 'paused'">
                            <div class="dock-flip-wrap overflow-hidden">
                                <div class="dock-flip-inner" x-bind:class="flipping ? 'is-flipping' : ''">
                                    <span class="nc-dock-title block truncate"
                                          x-bind:title="displayTitle ?? ''"
                                          x-text="displayTitle ?? ''"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Centre cell: the transport cluster, one housing, not three floating icons. --}}
        <div class="flex items-center rounded-[6px] border border-btn-border bg-btn p-1">
            <button type="button"
                    class="nc-bevel flex h-10 w-10 items-center justify-center rounded-[4px] border-r border-r-btn-border text-app-text text-[18px] hover:text-icon-hover"
                    @click="previous()" aria-label="Previous station">
                <x-icon.backward />
            </button>

            <button type="button"
                    class="nc-bevel flex h-12 w-12 items-center justify-center rounded-[4px] border-r border-r-btn-border text-app-text text-[22px] hover:text-icon-hover disabled:pointer-events-none disabled:opacity-[.48]"
                    x-bind:disabled="!current"
                    @click="current && toggle()"
                    x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                <template x-if="isPlaying"><x-icon.pause /></template>
                <template x-if="!isPlaying"><x-icon.play /></template>
            </button>

            <button type="button"
                    class="nc-bevel flex h-10 w-10 items-center justify-center rounded-[4px] text-app-text text-[18px] hover:text-icon-hover"
                    @click="next()" aria-label="Next station">
                <x-icon.forward />
            </button>
        </div>

        {{-- Right cell: shuffle, volume, colophon at >= 900px. --}}
        <div class="flex min-w-0 items-center justify-end gap-3">
            <button type="button"
                    class="nc-bevel flex h-9 w-9 items-center justify-center rounded-[4px] border border-btn-border bg-btn text-app-text text-[16px] hover:bg-btn-hover hover:text-icon-hover"
                    @click="shuffle()" aria-label="Shuffle stations">
                <x-icon.shuffle />
            </button>

            <input type="range" min="0" max="1" step="0.01"
                   class="volume-slider w-[120px]"
                   aria-label="Volume"
                   x-model.number="volume"
                   @input="setVolume($event.target.value)">

            <div class="nc-colophon flex items-center gap-2 truncate max-[899px]:hidden">
                <span class="truncate">Created by <a href="https://mariustanase.com" class="text-app-text no-underline hover:text-icon-hover">Marius Tanase</a></span>
                <a href="https://www.github.com/mariustanase" aria-label="GitHub" class="text-app-text shrink-0 text-[13px] no-underline hover:text-icon-hover"><x-icon.github /></a>
                <a href="https://www.linkedin.com/in/marius-tanase/" aria-label="LinkedIn" class="text-app-text shrink-0 text-[13px] no-underline hover:text-icon-hover"><x-icon.linkedin /></a>
            </div>
        </div>
    </div>

    {{-- Mobile (§5.4), <= 640px, 88px, two rows, everything reachable in one tap. --}}
    <div class="hidden h-full w-full flex-col max-[640px]:flex">
        <div class="flex h-[52px] items-center gap-3 px-4">
            <div class="nc-bevel relative h-8 w-8 shrink-0 overflow-hidden rounded-[4px] bg-menu-hover"
                 x-bind:class="!current ? 'nc-op-48' : ''">
                <template x-if="current">
                    <span class="nc-dock-monogram absolute inset-0 flex items-center justify-center text-[14px] leading-[14px]"
                          aria-hidden="true"
                          x-text="monogram(current.title)"></span>
                </template>
                <template x-if="current?.image">
                    <img class="absolute inset-0 h-full w-full object-cover"
                         x-bind:src="current.image"
                         alt=""
                         loading="lazy"
                         onerror="this.remove()">
                </template>
            </div>

            <div class="min-w-0 flex-1">
                <p class="nc-dock-name m-0 truncate"
                   x-bind:class="!current || dockState === 'failed' ? 'nc-op-72' : 'nc-op-100'"
                   x-bind:title="current?.title ?? ''"
                   x-text="current ? current.title : 'Pick a station'"></p>

                <div class="flex h-4 items-center gap-1.5">
                    <span class="nc-ember h-1.5 w-1.5 shrink-0"
                          x-show="dockState === 'connecting' || dockState === 'rebuffering' || dockState === 'playing'"
                          x-bind:class="dockState === 'playing' ? '' : 'nc-ember-static'"
                          aria-hidden="true"></span>

                    <div aria-live="polite" class="min-w-0 flex-1">
                        <template x-if="dockState === 'connecting' || dockState === 'rebuffering'">
                            <span class="nc-dock-status block truncate text-[11px] leading-4" x-text="statusText"></span>
                        </template>

                        <template x-if="dockState === 'failed'">
                            <p class="nc-dock-status m-0 truncate text-[11px] leading-4">
                                Didn't answer —
                                <button type="button"
                                        class="text-app-text underline underline-offset-2 hover:text-icon-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-icon-hover"
                                        @click="next()">Next ›</button>
                            </p>
                        </template>

                        <template x-if="dockState === 'playing' || dockState === 'paused'">
                            <div class="dock-flip-wrap overflow-hidden">
                                <div class="dock-flip-inner" x-bind:class="flipping ? 'is-flipping' : ''">
                                    <span class="nc-dock-title block truncate text-[12px] leading-4"
                                          x-bind:title="displayTitle ?? ''"
                                          x-text="displayTitle ?? ''"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <button type="button"
                    class="nc-bevel flex h-11 w-11 shrink-0 items-center justify-center rounded-[4px] border border-btn-border bg-btn text-app-text text-[20px] disabled:pointer-events-none disabled:opacity-[.48]"
                    x-bind:disabled="!current"
                    @click="current && toggle()"
                    x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                <template x-if="isPlaying"><x-icon.pause /></template>
                <template x-if="!isPlaying"><x-icon.play /></template>
            </button>
            <button type="button"
                    class="nc-bevel flex h-10 w-10 shrink-0 items-center justify-center rounded-[4px] border border-btn-border bg-btn text-app-text text-[16px]"
                    @click="next()" aria-label="Next station">
                <x-icon.forward />
            </button>
        </div>

        <div class="flex h-9 items-center gap-2 px-2">
            <button type="button"
                    class="nc-bevel flex h-9 w-9 shrink-0 items-center justify-center rounded-[4px] border border-btn-border bg-btn text-app-text text-[15px]"
                    @click="previous()" aria-label="Previous station">
                <x-icon.backward />
            </button>
            <button type="button"
                    class="nc-bevel flex h-9 w-9 shrink-0 items-center justify-center rounded-[4px] border border-btn-border bg-btn text-app-text text-[14px]"
                    @click="shuffle()" aria-label="Shuffle stations">
                <x-icon.shuffle />
            </button>
            <input type="range" min="0" max="1" step="0.01"
                   class="volume-slider flex-1"
                   aria-label="Volume"
                   x-model.number="volume"
                   @input="setVolume($event.target.value)">
        </div>
    </div>
</div>
