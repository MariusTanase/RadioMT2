{{--
    Zero-results state (§6.5). Buttons drop exactly one active constraint
    each, widest-first: search → language → country → genre → alive. When a
    search and a genre are both active, the two fold into one button ("Search
    all genres") rather than offering a redundant "clear search" next to it —
    the search stays, the genre restriction is what's lifted (§3.3 E4). If
    only one constraint is active, dropping it is identical to "Clear all",
    so only "Clear all" is shown.
--}}
@php
    $tokens = collect($filter->tokens())->keyBy('key');
    $constraints = [];

    if ($tokens->has('q')) {
        $constraints[] = $tokens->has('genre')
            ? ['label' => 'Search all genres', 'url' => $tokens['genre']['url']]
            : ['label' => 'Clear search', 'url' => $tokens['q']['url']];
    }

    if ($tokens->has('language')) {
        $constraints[] = ['label' => 'Remove '.$tokens['language']['label'], 'url' => $tokens['language']['url']];
    }

    if ($tokens->has('country')) {
        $constraints[] = ['label' => 'Remove '.$tokens['country']['label'], 'url' => $tokens['country']['url']];
    } elseif ($tokens->has('region')) {
        $constraints[] = ['label' => 'Remove '.$tokens['region']['label'], 'url' => $tokens['region']['url']];
    }

    if ($tokens->has('genre') && ! $tokens->has('q')) {
        $constraints[] = ['label' => 'Remove '.$tokens['genre']['label'], 'url' => $tokens['genre']['url']];
    }

    if ($filter->alive) {
        $constraints[] = [
            'label' => 'Include offline stations',
            'url' => url()->current().'?'.$filter->toQueryString(['alive' => '0', 'page' => null]),
        ];
    }

    // Narrative scope for the body line: genre · country/region · language ·
    // fav, then the quoted search term last — "No on-air stations match
    // Jazz · Iceland "bbc"." mirrors the count line's phrasing above it.
    $scopeParts = collect($filter->tokens())
        ->reject(fn (array $t) => $t['key'] === 'q')
        ->pluck('label')
        ->all();

    if ($filter->q !== null) {
        $scopeParts[] = '"'.$filter->q.'"';
    }
@endphp

<div class="flex h-[240px] flex-col items-center justify-center gap-4 text-center" aria-live="polite">
    <p class="text-app-text font-sans text-[20px] font-normal">Nothing here.</p>

    {{--
        With two or more active filters, restate the scope so the empty
        result doesn't look like a bug. With zero or one, that would just
        echo the one thing "Clear all" already says, so it's skipped in
        favour of the plain sentence (mirrors the button-count rule below).
    --}}
    @if ($tokens->count() > 1)
        <p class="font-opensans text-app-text max-w-[520px] text-[14px] leading-normal opacity-80">
            No {{ $filter->alive ? 'on-air ' : '' }}stations match {{ implode(' · ', $scopeParts) }}.
        </p>
    @else
        <p class="font-opensans text-app-text max-w-[520px] text-[14px] leading-normal opacity-80">
            No stations match your filters.
        </p>
    @endif

    <div class="flex flex-wrap items-center justify-center gap-2">
        @if (count($constraints) > 1)
            @foreach ($constraints as $constraint)
                <a class="border-btn-border bg-btn text-app-text hover:bg-btn-hover font-opensans rounded border px-3 py-2 text-[13px] font-semibold transition-colors duration-150"
                   href="{{ $constraint['url'] }}">{{ $constraint['label'] }}</a>
            @endforeach
        @endif

        <a class="border-btn-border bg-btn text-app-text hover:bg-btn-hover font-opensans rounded border px-3 py-2 text-[13px] font-semibold transition-colors duration-150"
           href="{{ route('radio.index') }}">Clear all</a>
    </div>
</div>
