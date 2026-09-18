{{--
    The manifest: a numbered, hairline-ruled, single-column list of stations
    (see docs/superpowers/specs/2026-09-18-radio-page-design-direction.md §4.5).

    No artwork per row — the design deliberately drops it (missing/broken for
    most of the catalogue) — which removes the row-height alignment problem,
    but titles still run to 255 characters (54 on page one alone), so the
    title cell is a single line, `truncate`d, with the full name in `title=`
    for anyone who needs it.

    `listeners` is NULL for the overwhelming majority of stations (only
    Icecast servers with a public status endpoint report one). Absence is the
    normal case: the "live" column renders nothing at all when it is null,
    never "0 listeners", and nothing here sorts by it.
--}}
@php
    // A row is tagged with one genre label, but stations are multi-genre —
    // `radios.genre` stores only the taxonomy's primary match. When a genre
    // filter is active every row in the result set matches it, so the
    // caption must say *that* genre, or the list looks wrong while being
    // right (the bug already reported for the old card grid).
    $filteredGenreLabel = $filter->genre
        ? config("radio-genres.genres.{$filter->genre}.label", $filter->genre)
        : null;

    $startRank = ($radios->currentPage() - 1) * $radios->perPage();
@endphp

<style>
    /* Pure-CSS "playing" glyph — no Web Audio analyser, no JS loop (§6, C6).
       Reduced motion collapses it to the single static bar §10.3 specifies. */
    .rmt-eq { display: inline-flex; align-items: flex-end; gap: 2px; height: 14px; }
    .rmt-eq span { display: block; width: 2px; background: currentColor; border-radius: 1px; animation: rmt-eq-bounce 900ms ease-in-out infinite; }
    .rmt-eq span:nth-child(2) { animation-delay: 200ms; }
    .rmt-eq span:nth-child(3) { animation-delay: 400ms; }
    @keyframes rmt-eq-bounce { 0%, 100% { height: 4px; } 50% { height: 14px; } }
    @media (prefers-reduced-motion: reduce) {
        .rmt-eq span { animation: none; height: 8px; }
        .rmt-eq span:nth-child(2), .rmt-eq span:nth-child(3) { display: none; }
    }
</style>

<ul class="m-0 list-none p-0" role="list" aria-label="Stations">
    @foreach ($radios as $i => $radio)
        @php
            $rank = $startRank + $i + 1;
            $genreCaption = $filteredGenreLabel ?? $radio->genre;
            $stream = collect([
                $radio->bitrate ? $radio->bitrate.'k' : null,
                $radio->codec,
            ])->filter()->implode(' · ');
        @endphp
        <li>
            <button type="button"
                    class="group grid h-12 w-full cursor-pointer items-center gap-3 border-b border-l-[3px] border-l-transparent px-3 text-left transition-colors duration-150 ease-out hover:bg-menu-hover focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-icon-hover max-[640px]:h-auto max-[640px]:min-h-14 max-[640px]:grid-cols-[56px_minmax(0,1fr)] max-[640px]:py-2 [grid-template-columns:64px_minmax(0,1fr)_120px_56px_104px_72px_88px] [border-bottom-color:color-mix(in_srgb,var(--color-app-text)_12%,transparent)]"
                    x-bind:class="current?.id === {{ $radio->id }} && isPlaying ? 'border-l-btn-border' : 'border-l-transparent'"
                    x-bind:aria-current="current?.id === {{ $radio->id }} && isPlaying ? 'true' : false"
                    aria-label="Play {{ $radio->title }}"
                    title="{{ $radio->title }}"
                    @click="select({{ $radio->id }})">

                {{-- Index: rank in the tuned set, or the equalizer glyph while this row plays. --}}
                <span class="justify-self-end text-right text-[13px] font-medium tabular-nums" style="min-width: 5ch">
                    <span x-show="!(current?.id === {{ $radio->id }} && isPlaying)">{{ number_format($rank) }}</span>
                    <span class="rmt-eq text-app-text justify-self-end" x-show="current?.id === {{ $radio->id }} && isPlaying" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </span>
                </span>

                {{-- Title (+ genre · country secondary line below 640px). --}}
                <div class="flex min-w-0 flex-col justify-center gap-0.5">
                    <span class="font-opensans truncate text-[15px] font-semibold">{{ $radio->title }}</span>
                    @if ($genreCaption || $radio->country_code)
                        <span class="font-opensans hidden truncate text-[12px] opacity-65 max-[640px]:block">
                            {{ $genreCaption }}{{ $genreCaption && $radio->country_code ? ' · ' : '' }}{{ $radio->country_code }}
                        </span>
                    @endif
                </div>

                {{-- Genre, country, stream, votes, live: desktop-only columns (§4.6 drops them below 640px). --}}
                <span class="font-opensans truncate text-[12px] opacity-65 max-[640px]:hidden">{{ $genreCaption }}</span>

                <span class="text-[13px] font-medium tracking-[0.04em] max-[640px]:hidden">{{ $radio->country_code }}</span>

                <span class="text-[13px] font-medium tabular-nums max-[640px]:hidden">{{ $stream }}</span>

                <span class="justify-self-end text-right text-[13px] font-medium tabular-nums max-[640px]:hidden">
                    @if (! is_null($radio->votes))
                        {{ number_format($radio->votes) }}
                    @endif
                </span>

                <span class="justify-self-end text-right text-[12px] font-medium tabular-nums max-[640px]:hidden">
                    @if (! is_null($radio->listeners))
                        <span aria-hidden="true">●</span> {{ number_format($radio->listeners) }}
                    @endif
                </span>
            </button>
        </li>
    @endforeach
</ul>
