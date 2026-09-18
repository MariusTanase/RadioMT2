@php
    $activeGenreLabel = $filter->genre
        ? (\App\Support\CatalogueFilter::SORT_LABELS ? config("radio-genres.genres.{$filter->genre}.label") : null)
        : null;
@endphp

<div role="list">
    @foreach ($radios as $radio)
        @php
            // A station is legitimately multi-genre; radios.genre stores only
            // the first match. When a genre filter is on, name the genre that
            // was filtered by or the row looks wrong while being right.
            $genre = $activeGenreLabel ?: $radio->genre;
            $bits = array_filter([$genre, $radio->country, $radio->bitrate ? $radio->bitrate.'k' : null]);
        @endphp
        <button type="button" role="listitem" class="station"
                title="{{ $radio->title }}"
                x-bind:aria-current="current?.id === {{ $radio->id }} ? 'true' : 'false'"
                @click="select({{ $radio->id }})">
            <span class="station-n">{{ $loop->iteration + ($radios->currentPage() - 1) * $radios->perPage() }}</span>
            <span class="station-body">
                <span class="station-name">{{ $radio->title }}</span>
                <span class="station-meta">
                    @foreach ($bits as $bit)<span>{{ $bit }}</span>@endforeach
                </span>
            </span>
            <span class="onair" x-bind:class="current?.id === {{ $radio->id }} && isPlaying ? 'is-live' : ''" aria-hidden="true"></span>
        </button>
    @endforeach
</div>
