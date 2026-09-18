{{--
    The genre link list — the ONE implementation of "all genres, as links"
    (design direction §5.2/E10). This partial is included three times by
    genre-band.blade.php (the pinned "All" slot, the horizontal scroller,
    and the "All genres" overlay grid) and never duplicated.

    Every item is a real <a href>, so genre selection works with JavaScript
    disabled. Selecting the already-active genre is a no-op — its href is
    identical to the current URL, so activating it just reloads the same
    view; "All" is the only control that clears the genre.

    Props (all optional except $filter and $genres):
      $filter               CatalogueFilter  current filter — required.
      $genres               list<array{slug:string,label:string}>  genres to
                             render, already ordered. Pass [] to render only
                             the "All" link (used for the band's pinned slot).
      $genreCounts          array<string,int>  slug => count. Default [].
      $genreCountsFallback  bool  true when $genreCounts is the global cache
                             standing in for a scoped count that was skipped
                             (§5.3). Renders counts with a dotted underline
                             instead of a spinner or blank. Default false.
      $includeAll            bool  prepend the "All" (clear-genre) link.
                             Default false.
      $variant                'band'|'overlay'  controls box sizing only —
                             the markup and behaviour are identical.
                             Default 'band'.
      $listClass              string|null  classes for the outer <ul>
                             (flex row for the band, grid for the overlay).
--}}
@php
    $genreCounts ??= [];
    $genreCountsFallback ??= false;
    $includeAll ??= false;
    $variant ??= 'band';

    $itemBoxClasses = $variant === 'overlay'
        ? 'w-full min-h-10 justify-center px-3 py-2'
        : 'h-10 w-24 shrink-0 justify-center px-2 max-[640px]:h-9 max-[640px]:w-[84px]';

    $countClasses = $genreCountsFallback
        ? 'underline decoration-dotted underline-offset-2'
        : '';
@endphp
<ul @class([$listClass ?? 'm-0 flex list-none items-center gap-2 p-0'])>
    @if ($includeAll)
        @php
            $allActive = $filter->genre === null;
            $allQs = $filter->toQueryString(['genre' => null, 'page' => null]);
            $allHref = route('radio.index').($allQs !== '' ? '?'.$allQs : '');
        @endphp
        <li class="shrink-0 list-none">
            <a href="{{ $allHref }}"
               @if ($allActive) aria-current="true" @endif
               class="nc-pill">
                <span class="flex items-baseline gap-1.5">
                    {{-- Non-colour selection cue: a filled dot, not colour alone (§5.2). --}}
                    @if ($allActive)
                        <span class="bg-app-text inline-block h-[5px] w-[5px] shrink-0 rounded-full" aria-hidden="true"></span>
                    @endif
                    All
                </span>
            </a>
        </li>
    @endif

    @foreach ($genres as $genre)
        @php
            $slug = $genre['slug'];
            $label = $genre['label'];
            $count = $genreCounts[$slug] ?? 0;
            $active = $filter->genre === $slug;
            $qs = $filter->toQueryString(['genre' => $slug, 'page' => null]);
            $href = route('radio.index').($qs !== '' ? '?'.$qs : '');
            $stationWord = $count === 1 ? 'station' : 'stations';
        @endphp
        <li class="shrink-0 list-none">
            <a href="{{ $href }}"
               aria-label="{{ $label }}, {{ number_format($count) }} {{ $stationWord }}"
               @if ($active) aria-current="true" @endif
               class="nc-pill">
                <span class="flex items-baseline gap-1.5" aria-hidden="true">
                    {{-- Non-colour selection cue: a filled dot, not colour alone (§5.2). --}}
                    @if ($active)
                        <span class="bg-app-text inline-block h-[5px] w-[5px] shrink-0 rounded-full"></span>
                    @endif
                    {{ $label }}
                </span>
                <span class="nc-pill-count" aria-hidden="true">
                    {{ number_format($count) }}
                </span>
            </a>
        </li>
    @endforeach
</ul>
