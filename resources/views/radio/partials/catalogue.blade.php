@php
    // Only what the player binds against. Serialised into every fragment
    // response, so nothing extra rides along.
    $stations = $radios->getCollection()->map(fn ($s) => [
        'id' => $s->id, 'title' => $s->title, 'genre' => $s->genre,
        'image' => $s->image, 'url' => $s->url,
    ])->values();

    $scope = collect($filter->tokens())->pluck('label')->implode(', ');
    $sortLabel = \App\Support\CatalogueFilter::SORT_LABELS[$filter->sort] ?? '';
@endphp

{{-- @json applies JSON_HEX_TAG, so a title containing </script> — these run to
     255 user-submitted characters — cannot break out of the block. --}}
<script type="application/json" id="catalogue-stations">@json($stations)</script>

<p class="meta-line">
    {{ number_format($radios->total()) }} {{ Str::plural('station', $radios->total()) }}@if ($scope) in {{ $scope }}@endif,
    {{ Str::lower($sortLabel) }}
    @if ($filter->isActive())
        &nbsp;<a href="{{ route('radio.index') }}" class="pager-link">Clear</a>
    @endif
</p>

@if ($radios->isEmpty())
    @include('radio.partials.empty-state')
@else
    @include('radio.partials.manifest')
    @include('radio.partials.folio')
@endif
