@if ($radios->lastPage() > 1)
    <nav class="pager" aria-label="Pagination">
        <a class="pager-link" href="{{ $radios->previousPageUrl() ?? '#' }}"
           @if ($radios->onFirstPage()) aria-disabled="true" @endif rel="prev">Previous</a>

        <span class="pager-pos">Page {{ number_format($radios->currentPage()) }} of {{ number_format($radios->lastPage()) }}</span>

        <a class="pager-link" href="{{ $radios->nextPageUrl() ?? '#' }}"
           @if (! $radios->hasMorePages()) aria-disabled="true" @endif rel="next">Next</a>
    </nav>
@endif
