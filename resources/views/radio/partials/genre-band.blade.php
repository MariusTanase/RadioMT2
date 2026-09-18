{{--
    The Band (design direction §4.3, §5) — a horizontal scroller of genre
    links with a pinned "All" clear-control on the left and a pinned
    "All genres" overlay trigger on the right, plus the overlay itself: the
    same 27 links in a 3-column grid (C5/E10). Both surfaces render
    partials/genre-links.blade.php, so there is one link implementation.

    Expects, optionally, from the including view/controller:
      $filter               CatalogueFilter  current filter — required.
      $genres               list<array{slug:string,label:string}>  ordered
                             per config('radio-genres.genres')'s `sort`.
                             Computed locally below when not supplied.
      $genreCounts          array<string,int>  slug => count to render.
                             Computed locally below when not supplied:
                             the globally-cached counts, or — when a
                             country/language/search filter is active — the
                             counts scoped to that filter (§5.3).
      $genreCountsFallback  bool  true when the scoped count query missed
                             its budget and $genreCounts fell back to the
                             global cache. Computed locally when not
                             supplied.

    Counts always come from App\Support\CatalogueCounts's cached
    aggregates — never a per-request join per genre (§5.3, hard constraint).
--}}
@php
    $genres ??= collect(config('radio-genres.genres', []))
        ->sortBy(fn (array $g) => $g['sort'] ?? 0)
        ->map(fn (array $g, string $slug) => ['slug' => $slug, 'label' => $g['label']])
        ->values()
        ->all();

    if (! isset($genreCounts)) {
        $otherFiltersActive = $filter->q !== null
            || $filter->country !== null
            || $filter->region !== null
            || $filter->language !== null;

        if ($otherFiltersActive) {
            // §5.3: one grouped query, scoped to every filter except genre.
            // If it misses its ~40ms budget, fall back to the cheap global
            // cache rather than block the band on a slow aggregate.
            $countStartedAt = microtime(true);

            try {
                $genreCounts = \App\Support\CatalogueCounts::scopedGenreCounts($filter);
                $genreCountsFallback = (microtime(true) - $countStartedAt) > 0.04;

                if ($genreCountsFallback) {
                    $genreCounts = \App\Support\CatalogueCounts::genreCounts();
                }
            } catch (\Throwable $e) {
                $genreCounts = \App\Support\CatalogueCounts::genreCounts();
                $genreCountsFallback = true;
            }
        } else {
            $genreCounts = \App\Support\CatalogueCounts::genreCounts();
            $genreCountsFallback = false;
        }
    }

    $genreCountsFallback ??= false;
@endphp

<div class="bg-menu sticky top-[56px] z-20 flex h-14 items-stretch border-b border-[color:color-mix(in_srgb,var(--color-app-text)_12%,transparent)] max-[640px]:h-11"
     x-show="!uiHidden"
     x-data="{
         genreOverlayOpen: false,
         openGenreOverlay() {
             this.genreOverlayOpen = true;
             $nextTick(() => $refs.genreOverlayPanel?.querySelector('a, button')?.focus());
         },
         closeGenreOverlay() {
             this.genreOverlayOpen = false;
             $refs.allGenresButton?.focus();
         },
     }"
     x-on:keydown.escape.window="if (genreOverlayOpen) closeGenreOverlay()">

    {{-- Pinned "All" (clear genre) control — outside the scroller, never scrolls away (§4.3). --}}
    <div class="bg-menu flex shrink-0 items-center pl-4 max-[640px]:pl-3">
        @include('radio.partials.genre-links', [
            'filter' => $filter,
            'genres' => [],
            'includeAll' => true,
            'variant' => 'band',
            'genreCounts' => $genreCounts,
            'genreCountsFallback' => $genreCountsFallback,
        ])
    </div>

    {{--
        The scrollable genre row. A plain list of links: Tab moves through
        it, arrow keys scroll the container as the browser intends — no
        roving tabindex, no arrow-key hijacking (§5.5).
    --}}
    <nav aria-label="Genres"
         x-ref="genreScroller"
         x-init="$nextTick(() => $el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', behavior: 'auto' }))"
         class="flex flex-1 items-center overflow-x-auto [-ms-overflow-style:none] [scroll-snap-type:x_proximity] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @include('radio.partials.genre-links', [
            'filter' => $filter,
            'genres' => $genres,
            'includeAll' => false,
            'variant' => 'band',
            'genreCounts' => $genreCounts,
            'genreCountsFallback' => $genreCountsFallback,
            'listClass' => 'm-0 flex list-none items-center gap-2 p-2',
        ])
    </nav>

    {{-- Pinned "All genres" overlay trigger — outside the scroller, never scrolls away (§4.3). --}}
    <div class="bg-menu flex shrink-0 items-center pr-4 max-[640px]:pr-3">
        <button type="button"
                x-ref="allGenresButton"
                x-on:click="genreOverlayOpen ? closeGenreOverlay() : openGenreOverlay()"
                x-bind:aria-expanded="genreOverlayOpen.toString()"
                aria-controls="genre-overlay"
                class="border-btn-border bg-btn hover:bg-btn-hover text-app-text flex h-10 shrink-0 items-center justify-center rounded-md border px-3 font-sans text-[13px] font-semibold whitespace-nowrap outline-none transition-colors duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-icon-hover max-[640px]:h-9 max-[640px]:px-2 max-[640px]:text-xs">
            All genres
        </button>
    </div>

    {{-- "All genres" overlay: the same 27 links, in a grid (C5/§5.2). --}}
    <div id="genre-overlay"
         x-show="genreOverlayOpen"
         x-cloak
         style="display: none;"
         x-on:click.self="closeGenreOverlay()"
         role="dialog"
         aria-modal="true"
         aria-label="All genres"
         class="fixed inset-0 z-30 flex items-start justify-center bg-[color:color-mix(in_srgb,var(--color-app)_55%,transparent)] p-4 pt-24">
        <div x-ref="genreOverlayPanel"
             x-on:keydown.tab="(event) => {
                 const focusables = $el.querySelectorAll('a, button');
                 if (! focusables.length) return;
                 const first = focusables[0];
                 const last = focusables[focusables.length - 1];
                 if (event.shiftKey && document.activeElement === first) {
                     event.preventDefault();
                     last.focus();
                 } else if (! event.shiftKey && document.activeElement === last) {
                     event.preventDefault();
                     first.focus();
                 }
             }"
             class="bg-menu max-h-[70vh] w-full max-w-[900px] overflow-y-auto rounded-lg p-4 shadow-[0_18px_28px_var(--color-menu)]">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-app-text font-opensans text-base font-semibold">All genres</h2>
                <button type="button"
                        x-on:click="closeGenreOverlay()"
                        aria-label="Close all genres"
                        class="text-app-text hover:text-icon-hover focus-visible:outline-icon-hover border-none bg-transparent text-2xl leading-none outline-none focus-visible:outline-2 focus-visible:outline-offset-2">
                    &times;
                </button>
            </div>

            @include('radio.partials.genre-links', [
                'filter' => $filter,
                'genres' => $genres,
                'includeAll' => false,
                'variant' => 'overlay',
                'genreCounts' => $genreCounts,
                'genreCountsFallback' => $genreCountsFallback,
                'listClass' => 'm-0 grid grid-cols-3 gap-3 p-0 max-[640px]:grid-cols-2',
            ])
        </div>
    </div>
</div>
