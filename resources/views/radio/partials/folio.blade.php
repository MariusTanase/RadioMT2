{{--
    Folio: prev/next, a windowed page-number list, a "Go to" input and the
    "Page X of Y" readout (§4.5, §6.6). Server-side paging only — 50 rows per
    page, no infinite scroll, so the client never holds more than one page
    and the audio stream never competes with a background list fetch.

    Links reuse the paginator's own URL builder ($radios->url()) so they
    always agree with whatever base path rendered this fragment (the full
    page load or the AJAX swap), without this partial needing to know which.
--}}
@php
    $current = $radios->currentPage();
    $last = $radios->lastPage();

    // A window of one page either side of the current one, plus the first
    // and last page with an ellipsis to bridge the gap — at most 7 slots.
    $window = collect(range(max(1, $current - 1), min($last, $current + 1)));

    $pages = collect();
    if ($window->first() > 1) {
        $pages->push(1);
        if ($window->first() > 2) {
            $pages->push('…');
        }
    }
    $pages = $pages->merge($window);
    if ($window->last() < $last) {
        if ($window->last() < $last - 1) {
            $pages->push('…');
        }
        $pages->push($last);
    }
@endphp

{{--
    Typography/colour come from the shared "Night Console" CSS foundation
    (resources/css/app.css): .nc-folio for the pagination type scale (Sono
    13/18/500 at 72%), .nc-bevel for every pressable control's lit/shadow
    edges and :active swap, and .nc-op-48 to drop a disabled control to the
    third opacity tier. The current-page marker is shape (a 2px underline),
    never colour fill alone (§9.2's three-tier opacity law).
--}}
<nav aria-label="Pagination" class="mt-4 flex flex-wrap items-center justify-center gap-3 pb-2" x-data="{ showGoTo: false }">
    @if ($radios->onFirstPage())
        <span class="nc-folio nc-bevel nc-op-48 border-btn-border bg-btn inline-flex min-h-11 items-center rounded-[4px] border px-3 py-2" aria-disabled="true">Prev</span>
    @else
        <a class="nc-folio nc-bevel border-btn-border bg-btn hover:bg-btn-hover inline-flex min-h-11 items-center rounded-[4px] border px-3 py-2"
           href="{{ $radios->previousPageUrl() }}" rel="prev">Prev</a>
    @endif

    {{-- Desktop: the numbered window. Hidden below 640px in favour of the "Page X of Y" toggle. --}}
    <div class="flex items-center gap-1 max-[640px]:hidden">
        @foreach ($pages as $page)
            @if ($page === '…')
                <span class="nc-folio nc-op-48 px-1" aria-hidden="true">…</span>
            @elseif ($page === $current)
                <span class="nc-folio nc-op-100 border-btn-border px-2 py-1 font-semibold" style="border-bottom: 2px solid var(--color-btn-border)" aria-current="page">{{ number_format($page) }}</span>
            @else
                <a class="nc-folio hover:bg-menu-hover rounded-[4px] px-2 py-1 transition-colors duration-[120ms] ease-out hover:opacity-100"
                   href="{{ $radios->url($page) }}">{{ number_format($page) }}</a>
            @endif
        @endforeach
    </div>

    @if ($radios->hasMorePages())
        <a class="nc-folio nc-bevel border-btn-border bg-btn hover:bg-btn-hover inline-flex min-h-11 items-center rounded-[4px] border px-3 py-2"
           href="{{ $radios->nextPageUrl() }}" rel="next">Next</a>
    @else
        <span class="nc-folio nc-bevel nc-op-48 border-btn-border bg-btn inline-flex min-h-11 items-center rounded-[4px] border px-3 py-2" aria-disabled="true">Next</span>
    @endif

    {{-- Desktop: always-visible "Go to" input. --}}
    <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2 max-[640px]:hidden">
        @foreach (request()->except('page') as $key => $value)
            @foreach (\Illuminate\Support\Arr::wrap($value) as $v)
                <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">
            @endforeach
        @endforeach
        <label class="nc-folio" for="folio-page">Go to</label>
        <input class="nc-bevel border-btn-border bg-menu text-app-text w-16 rounded-[4px] border px-2 py-1 text-center text-[13px] tabular-nums"
               type="number" id="folio-page" name="page" min="1" max="{{ $last }}" value="{{ $current }}" aria-label="Go to page">
    </form>

    <span class="nc-folio max-[640px]:hidden">Page {{ number_format($current) }} of {{ number_format($last) }}</span>

    {{-- Mobile: tapping the page readout reveals the "Go to" input. --}}
    <button type="button"
            class="nc-folio hidden min-h-11 items-center px-2 max-[640px]:inline-flex"
            style="border-bottom: 2px solid var(--color-btn-border)"
            x-on:click="showGoTo = !showGoTo"
            x-bind:aria-expanded="showGoTo.toString()">
        Page {{ number_format($current) }} of {{ number_format($last) }}
    </button>

    <form method="GET" action="{{ request()->url() }}" class="flex w-full items-center justify-center gap-2" x-show="showGoTo">
        @foreach (request()->except('page') as $key => $value)
            @foreach (\Illuminate\Support\Arr::wrap($value) as $v)
                <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">
            @endforeach
        @endforeach
        <label class="nc-folio" for="folio-page-mobile">Go to</label>
        <input class="nc-bevel border-btn-border bg-menu text-app-text w-16 rounded-[4px] border px-2 py-1 text-center text-[13px] tabular-nums"
               type="number" id="folio-page-mobile" name="page" min="1" max="{{ $last }}" value="{{ $current }}" aria-label="Go to page">
    </form>
</nav>
