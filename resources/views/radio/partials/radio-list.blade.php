@php
    // Monogram fallback: 18,056 of ~49,000 stations have no artwork, and many
    // stored URLs are dead. A broken-image icon must never render.
    $monogram = static function (string $title): string {
        $letters = preg_replace('/[^\p{L}\p{N}]/u', '', $title) ?? '';

        return $letters === '' ? '?' : mb_strtoupper(mb_substr($letters, 0, 1));
    };

    // Stations are legitimately multi-genre: one real row carries
    // [pop, dance, electronic, house, hip-hop]. `radios.genre` stores only the
    // first match in taxonomy order, so filtering House used to show cards
    // captioned "Pop". When a genre filter is active the caption must name the
    // genre you actually filtered by, or the page looks broken while being right.
    $activeGenreName = $activeGenre
        ? ($genres->firstWhere('slug', $activeGenre)?->name ?? $activeGenre)
        : null;
@endphp

<div class="mx-auto my-8 max-h-fit w-full max-w-[1200px] max-[1200px]:w-[80%] max-[800px]:w-[90%] max-[720px]:mb-24"
     x-show="!uiHidden">

    {{-- Count line: restate the scope so you always know where you are. --}}
    <p class="text-app-text mb-4 text-center text-[13px] opacity-70">
        {{ number_format($radios->total()) }}
        {{ Str::plural('station', $radios->total()) }}
        @if ($activeGenre)
            in {{ $activeGenreName }}
        @endif
        @if ($radios->lastPage() > 1)
            · page {{ number_format($radios->currentPage()) }} of {{ number_format($radios->lastPage()) }}
        @endif
    </p>

    @if ($radios->isEmpty())
        <div class="flex h-[240px] flex-col items-center justify-center gap-4">
            <p class="text-app-text text-[20px]">Nothing here.</p>
            <a class="border-btn-border bg-btn text-app-text hover:bg-btn-hover rounded border px-3 py-2 text-sm font-bold transition-all duration-300"
               href="{{ route('radio.index') }}">Show all stations</a>
        </div>
    @else
        <ul class="mx-auto flex w-full list-none flex-wrap items-center justify-center gap-4 p-0">
            @foreach ($radios as $radio)
                {{--
                    Fixed height, and the title is hard-clamped to two lines. Station names
                    run to 255 characters in this catalogue ("WDR Sportschau - 2. Fussball
                    Bundesliga-Konferenz LIVE" is 54 on page one alone); unclamped they made
                    every card a different height and the grid went ragged.
                --}}
                <li class="bg-menu text-app-text hover:bg-menu-hover group flex h-[84px] w-[218px] cursor-pointer flex-row items-center gap-3 rounded-[10px] p-2.5 transition-colors duration-200 max-[720px]:h-[76px] max-[720px]:w-[168px]"
                    title="{{ $radio->title }}"
                    @click="select({{ $radio->id }})">
                    <div class="relative h-[56px] w-[56px] shrink-0 max-[720px]:h-[48px] max-[720px]:w-[48px]">
                        {{-- Monogram sits underneath; a missing or broken <img> simply reveals it. --}}
                        <span class="bg-menu-hover text-app-text border-app-text absolute inset-0 flex items-center justify-center rounded-full border text-[20px] font-light"
                              aria-hidden="true">{{ $monogram($radio->title) }}</span>

                        @if ($radio->image)
                            <img class="absolute inset-0 h-full w-full rounded-full object-cover"
                                 src="{{ $radio->image }}" alt="" loading="lazy"
                                 onerror="this.remove()">
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-col justify-center gap-0.5">
                        <h4 class="line-clamp-2 text-[13px] leading-[1.25] font-semibold break-words">{{ $radio->title }}</h4>
                        @if ($activeGenreName ?? $radio->genre)
                            <span class="truncate text-[11px] opacity-60">{{ $activeGenreName ?? $radio->genre }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($radios->lastPage() > 1)
            <nav aria-label="Pagination" class="mt-8 flex items-center justify-center gap-3">
                @if ($radios->onFirstPage())
                    <span class="border-btn-border text-app-text rounded border px-3 py-2 text-sm font-bold opacity-40">Previous</span>
                @else
                    <a class="border-btn-border bg-btn text-app-text hover:bg-btn-hover rounded border px-3 py-2 text-sm font-bold transition-all duration-300"
                       href="{{ $radios->previousPageUrl() }}" rel="prev">Previous</a>
                @endif

                <form method="GET" action="{{ route('radio.index') }}" class="flex items-center gap-2">
                    @if ($activeGenre)
                        <input type="hidden" name="genre" value="{{ $activeGenre }}">
                    @endif
                    <label class="text-app-text text-[13px] opacity-70" for="page">Go to</label>
                    <input class="border-btn-border bg-menu text-app-text w-[5.5rem] rounded border px-2 py-1 text-center text-sm"
                           type="number" id="page" name="page" min="1" max="{{ $radios->lastPage() }}"
                           value="{{ $radios->currentPage() }}" aria-label="Page number">
                </form>

                @if ($radios->hasMorePages())
                    <a class="border-btn-border bg-btn text-app-text hover:bg-btn-hover rounded border px-3 py-2 text-sm font-bold transition-all duration-300"
                       href="{{ $radios->nextPageUrl() }}" rel="next">Next</a>
                @else
                    <span class="border-btn-border text-app-text rounded border px-3 py-2 text-sm font-bold opacity-40">Next</span>
                @endif
            </nav>
        @endif
    @endif
</div>
