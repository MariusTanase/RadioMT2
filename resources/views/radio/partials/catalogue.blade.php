{{--
    The swappable #catalogue fragment: filter tokens, the count line, the
    manifest (or the empty state) and the folio. Rendered by both the full
    page load and the AJAX fragment endpoint (CatalogueController::index),
    so it stays one template for both call sites rather than two
    implementations that can drift apart (§6.1).
--}}
@php
    // Scope sentence for the count line: "in Jazz · Germany · matching
    // \"bbc\"" (§6.4). Search is phrased as "matching" rather than folded
    // into the "in ..." list, per the design direction's own example.
    $scopeInParts = collect($filter->tokens())
        ->reject(fn (array $t) => $t['key'] === 'q')
        ->pluck('label')
        ->implode(' · ');

    // Reduced to exactly what the player binds against (id/title/genre/image/url):
    // this gets serialised into every fragment response, so nothing extra rides along.
    $stations = $radios->getCollection()->map(fn ($r) => [
        'id' => $r->id,
        'title' => $r->title,
        'genre' => $r->genre,
        'image' => $r->image,
        'url' => $r->url,
    ])->values();

    // §7.2: the count line always states scope AND order in words, so a
    // returning-to-defaults click never looks unexplained. Mirrors
    // tune-bar.blade.php's own $sortOptions verbatim — duplicated rather
    // than shared because that partial is out of this file's scope right
    // now; whoever renames these per §8 ("Popular worldwide" / "Most
    // played") should update both lists together.
    $sortLabels = \App\Support\CatalogueFilter::SORT_LABELS;
    $sortLabel = $sortLabels[$filter->sort] ?? $sortLabels['played'];

    // §7.2: "No play data below this point — ordered A–Z" once every row on
    // the current page has a zero ordering signal for the active sort.
    // Computed from the rendered page, so it costs nothing extra.
    $sortSignalExhausted = $radios->getCollection()->isNotEmpty() && match ($filter->sort) {
        'voted' => $radios->getCollection()->every(fn ($r) => (int) ($r->votes ?? 0) === 0),
        'bitrate' => $radios->getCollection()->every(fn ($r) => empty($r->bitrate)),
        'az', 'country' => false,
        default => $radios->getCollection()->every(
            fn ($r) => (int) ($r->play_count ?? 0) === 0 && (int) ($r->click_count ?? 0) === 0
        ),
    };
@endphp

<div id="catalogue" class="mx-auto w-full max-w-[1100px] px-6 max-[640px]:px-4">
    {{--
        Data island for the audio player, which lives outside #catalogue and
        never gets touched by the fragment swap. catalogue.js reads this
        after every navigation and dispatches `catalogue:swapped` so
        next()/previous() walk the page that's actually on screen, not the
        one the document booted with. @json (not json_encode) applies
        JSON_HEX_TAG, so a station title containing `</script>` — titles here
        run to 255 user-submitted characters — can't break out of the block.
    --}}
    <script type="application/json" id="catalogue-stations">@json($stations)</script>

    {{--
        The active-filter chips live in the tune bar, which is sticky, so they
        stay reachable while the list scrolls. Rendering them here as well put
        the same row on screen twice.
    --}}

    {{-- Typography from the shared "Night Console" foundation: .nc-count-line is Sono 13/18/500 at 72%. --}}
    <p @class([
        'nc-count-line text-app-text text-right',
        'mb-1' => $sortSignalExhausted,
        'mb-2' => ! $sortSignalExhausted,
    ]) aria-live="polite">
        {{ number_format($radios->total()) }} {{ Str::plural('station', $radios->total()) }}
        @if ($scopeInParts !== '')
            in {{ $scopeInParts }}
        @endif
        @if ($filter->q !== null)
            {{ $scopeInParts !== '' ? '·' : '' }} matching "{{ $filter->q }}"
        @endif
        · sorted by {{ $sortLabel }}
    </p>

    @if ($sortSignalExhausted)
        <p class="nc-count-line nc-op-48 text-app-text mb-2 text-right">
            No play data below this point — ordered A–Z.
        </p>
    @endif

    @if ($radios->isEmpty())
        @include('radio.partials.empty-state', ['filter' => $filter])
    @else
        @include('radio.partials.manifest', ['radios' => $radios, 'filter' => $filter])

        @if ($radios->lastPage() > 1)
            @include('radio.partials.folio', ['radios' => $radios])
        @endif
    @endif
</div>
