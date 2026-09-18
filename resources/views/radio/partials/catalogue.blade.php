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

    @if ($filter->tokens())
        <div class="mb-3 flex flex-wrap items-center gap-2" role="list" aria-label="Active filters">
            @foreach ($filter->tokens() as $token)
                <a href="{{ $token['url'] }}"
                   role="listitem"
                   class="border-btn-border bg-btn text-app-text hover:bg-btn-hover font-opensans inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[12px] font-semibold transition-colors duration-150">
                    {{ $token['label'] }}
                    <span aria-hidden="true">&times;</span>
                </a>
            @endforeach

            <a href="{{ route('radio.index') }}"
               class="text-app-text font-opensans text-[12px] font-semibold opacity-70 underline decoration-1 underline-offset-2 hover:opacity-100">
                Clear all
            </a>
        </div>
    @endif

    <p class="text-app-text font-opensans mb-2 text-right text-[14px] font-semibold" aria-live="polite">
        {{ number_format($radios->total()) }} {{ Str::plural('station', $radios->total()) }}
        @if ($scopeInParts !== '')
            in {{ $scopeInParts }}
        @endif
        @if ($filter->q !== null)
            {{ $scopeInParts !== '' ? '·' : '' }} matching "{{ $filter->q }}"
        @endif
    </p>

    @if ($radios->isEmpty())
        @include('radio.partials.empty-state', ['filter' => $filter])
    @else
        @include('radio.partials.manifest', ['radios' => $radios, 'filter' => $filter])

        @if ($radios->lastPage() > 1)
            @include('radio.partials.folio', ['radios' => $radios])
        @endif
    @endif
</div>
