{{--
    The manifest: The Night Console's ledger of stations (design direction
    docs/superpowers/specs/2026-09-18-posh-redesign-direction.md §7.1). This
    is an evolution of the old seven-column ledger, not a reinvention: seven
    columns become two lines (title + one dim caption), bordered metadata
    chips are gone entirely, the hover-only affordance becomes an
    always-visible glyph, and a rank folio replaces the page-local index.

    Type, opacity and the bevel/ember mechanics come from the shared "Night
    Console" CSS foundation in resources/css/app.css (.nc-row, .nc-row-folio,
    .nc-row-title, .nc-row-caption, .nc-bevel, .nc-ember) rather than being
    hand-rolled here, so every surface on the page shares one vocabulary.

    No artwork per row — the design deliberately drops it (missing/broken for
    37%+ of the catalogue) — but titles still run to 255 characters, so the
    title is a single line, `truncate`d, with the full name in `title=` for
    anyone who needs it.

    Selection/playing state is shown by SHAPE — the 3px left edge, the
    breathing ember dot replacing the rocker glyph, `aria-current` — never by
    colour fill alone, so it survives all four themes.

    `listeners` and `votes` are NULL for the overwhelming majority of
    stations. They leave the resting row entirely (§7.1: "survive only in
    the row's title= tooltip") — rendered as nothing when both are null,
    never "0 votes" / "0 listeners", and nothing here sorts by them.
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
    /* The rocker glyph's own rest/hover opacity isn't among the shared
       foundation's provided classes (resources/css/app.css) — this single
       rule fills that one gap, modeled on that file's own .nc-row-title
       hover/focus/current lift, so it stays consistent with the same
       three-tier law rather than inventing a fourth. */
    .nc-row-glyph { opacity: .48; transition: opacity 120ms ease-out; }
    .nc-row:hover .nc-row-glyph,
    .nc-row:focus-visible .nc-row-glyph,
    .nc-row.is-active .nc-row-glyph,
    .nc-row[aria-current='true'] .nc-row-glyph { opacity: 1; }
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

            // Cell 3's caption — Genre · Country · 128k · MP3 — with each
            // field omitted (never dashed, never "0") when absent, identical
            // at every width (§7.1).
            $caption = collect([$genreCaption, $radio->country_code, $stream])->filter()->implode(' · ');

            // Votes and listener counts are sparse and mostly null, so they
            // leave the resting row and survive only as a tooltip (§7.1).
            $metaTooltip = collect([
                ! is_null($radio->votes) ? number_format($radio->votes).' votes' : null,
                ! is_null($radio->listeners) ? number_format($radio->listeners).' listeners' : null,
            ])->filter()->implode(' · ');
        @endphp
        <li>
            <button type="button"
                    class="nc-row group grid min-h-12 w-full cursor-pointer grid-cols-[28px_56px_minmax(0,1fr)] items-center gap-3 border-b border-l-[3px] border-l-transparent px-3 text-left transition-[background-color] duration-[120ms] ease-out hover:bg-[color:color-mix(in_srgb,var(--color-btn-border)_6%,transparent)] focus-visible:bg-[color:color-mix(in_srgb,var(--color-btn-border)_6%,transparent)] focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-icon-hover max-[640px]:min-h-16 [border-bottom-color:color-mix(in_srgb,var(--color-app-text)_10%,transparent)]"
                    x-bind:class="current?.id === {{ $radio->id }} && isPlaying ? 'is-active border-l-btn-border' : 'border-l-transparent'"
                    x-bind:aria-current="current?.id === {{ $radio->id }} && isPlaying ? 'true' : false"
                    aria-label="Play {{ $radio->title }}"
                    title="{{ $radio->title }}"
                    @click="select({{ $radio->id }})">

                {{-- Cell 1 (28px): the play indicator — bevelled rocker at rest,
                     breathing ember dot while this row is playing. --}}
                <span class="justify-self-start" aria-hidden="true">
                    <span class="nc-row-glyph nc-bevel bg-btn border-btn-border inline-flex h-5 w-5 items-center justify-center rounded-[4px] border"
                          x-show="!(current?.id === {{ $radio->id }} && isPlaying)">
                        <span class="block h-0 w-0 border-y-[4px] border-l-[6px] border-y-transparent" style="border-left-color: var(--color-app-text)"></span>
                    </span>
                    <span class="nc-ember h-[9px] w-[9px]" x-show="current?.id === {{ $radio->id }} && isPlaying"></span>
                </span>

                {{-- Cell 2 (56px): rank folio — position in the whole filtered,
                     sorted set, a house number rather than a scoreboard. --}}
                <span class="nc-row-folio justify-self-end">
                    {{ number_format($rank) }}
                </span>

                {{-- Cell 3: title + one dim caption, identical at every width. --}}
                <div class="flex min-w-0 flex-col justify-center gap-0.5">
                    <span class="nc-row-title truncate">
                        {{ $radio->title }}
                    </span>
                    @if ($caption !== '')
                        <span class="nc-row-caption truncate"
                              @if ($metaTooltip !== '') title="{{ $metaTooltip }}" @endif>
                            {{ $caption }}
                        </span>
                    @endif
                </div>
            </button>
        </li>
    @endforeach
</ul>
