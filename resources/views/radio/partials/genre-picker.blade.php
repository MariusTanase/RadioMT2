{{--
    Genre picker. Real <a> elements with real hrefs, so it works without JS,
    is keyboard-navigable by default, and every view is linkable.
    Source of truth is the `genres` table, seeded from config/radio-genres.php.
--}}
<nav aria-label="Genres" class="mx-auto mt-8 w-full max-w-[1200px] max-[1200px]:w-[80%] max-[800px]:w-[90%]"
     x-show="!uiHidden">
    <ul class="flex list-none flex-wrap items-center justify-center gap-2 p-0">
        <li>
            <a href="{{ route('radio.index') }}"
               @class([
                   'border-btn-border text-app-text inline-flex items-center gap-2 rounded-md border px-3 py-2 text-[13px] font-semibold transition-all duration-200',
                   'bg-btn-hover shadow-[inset_0_1px_3px_rgb(0_0_0/0.35)]' => $activeGenre === null,
                   'bg-btn hover:bg-btn-hover' => $activeGenre !== null,
               ])
               @if ($activeGenre === null) aria-current="true" @endif>
                @if ($activeGenre === null)
                    <span class="bg-app-text inline-block h-[5px] w-[5px] rounded-full" aria-hidden="true"></span>
                @endif
                All
            </a>
        </li>

        @foreach ($genres as $genre)
            <li>
                <a href="{{ route('radio.index', ['genre' => $genre->slug]) }}"
                   @class([
                       'border-btn-border text-app-text inline-flex items-center gap-2 rounded-md border px-3 py-2 text-[13px] font-semibold transition-all duration-200',
                       'bg-btn-hover shadow-[inset_0_1px_3px_rgb(0_0_0/0.35)]' => $activeGenre === $genre->slug,
                       'bg-btn hover:bg-btn-hover' => $activeGenre !== $genre->slug,
                   ])
                   @if ($activeGenre === $genre->slug) aria-current="true" @endif>
                    {{-- The dot is the non-colour cue: selection must be readable without relying on fill alone. --}}
                    @if ($activeGenre === $genre->slug)
                        <span class="bg-app-text inline-block h-[5px] w-[5px] rounded-full" aria-hidden="true"></span>
                    @endif
                    {{ $genre->name }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
