{{--
    Genres read as an index line rather than a row of chips: at 33 of them,
    boxes turn the top of the page into a grid of buttons competing with the
    list underneath. "All genres" keeps its exact wording — a test asserts it.
--}}
<nav class="genres" aria-label="Genres">
    <a class="genre" href="{{ route('radio.index') }}"
       @if (! $filter->genre) aria-current="true" @endif>All genres</a>

    @foreach ($genres as $g)
        @php
            $slug = is_array($g) ? $g['slug'] : $g->slug;
            $label = is_array($g) ? $g['label'] : $g->name;
            $qs = $filter->toQueryString(['genre' => $slug, 'page' => null]);
        @endphp
        <a class="genre" href="{{ route('radio.index').($qs !== '' ? '?'.$qs : '') }}"
           @if ($filter->genre === $slug) aria-current="true" @endif
           aria-label="{{ $label }}, {{ number_format($genreCounts[$slug] ?? 0) }} stations">
            {{ $label }}<span class="genre-n">{{ number_format($genreCounts[$slug] ?? 0) }}</span>
        </a>
    @endforeach
</nav>
