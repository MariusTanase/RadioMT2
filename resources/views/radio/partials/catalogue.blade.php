{{-- Placeholder fragment. Another agent replaces this with the real manifest
     (tokens row, count line, station cards, folio). Kept deliberately
     minimal — just enough to prove CatalogueController's query and paging
     work: the total count and a plain list of station titles. --}}
<div id="catalogue">
    <p>{{ $radios->total() }} station{{ $radios->total() === 1 ? '' : 's' }}</p>

    <ul>
        @forelse ($radios as $radio)
            <li>{{ $radio->title }}</li>
        @empty
            <li>No stations match your filters.</li>
        @endforelse
    </ul>
</div>
