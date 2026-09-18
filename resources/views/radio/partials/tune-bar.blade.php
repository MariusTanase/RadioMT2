{{--
    The Tune bar (design direction §4.4, §6.2, §6.3, §6.4) — favourites
    toggle, search, country/language/sort filters, the on-air toggle, and
    the removable active-filter token row. One <form method="GET"> so the
    whole bar works with JavaScript disabled: submitting it performs an
    ordinary navigation to the same URL a JS-driven fetch would build.
    Alpine only adds auto-submit-on-change; nothing here is JS-only.

    Expects, from the including view/controller:
      $filter     CatalogueFilter  current filter — required.
      $languages  array<string,int>  language => count, SCOPED to the
                   current result set (§6.3 — never a global list). Defaults
                   to [] (renders "All languages" only) when not supplied;
                   the caller is responsible for scoping it, since that
                   requires knowledge of the live result set this partial
                   does not have on its own.

    Country names and counts, and the continent grouping, are derived here
    from App\Support\CatalogueCounts::countryCounts() (cached 24h) and
    config('continents.php'), plus a locally-cached country_code => name
    lookup — the data contract provides codes and alive-counts but no
    display names, so this partial builds and caches that mapping itself
    rather than joining per request.
--}}
@php
    $languages ??= [];

    $countryCounts = \App\Support\CatalogueCounts::countryCounts();

    $countryNames = \Illuminate\Support\Facades\Cache::remember(
        'catalogue:country-names',
        60 * 60 * 24,
        fn () => \Illuminate\Support\Facades\DB::table('radios')
            ->whereNotNull('country_code')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->selectRaw('country_code, max(country) as country_name')
            ->groupBy('country_code')
            ->pluck('country_name', 'country_code')
            ->all()
    );

    $continentGroups = [];
    foreach (config('continents', []) as $code => $continent) {
        $continentGroups[$continent][] = [
            'code' => $code,
            'name' => $countryNames[$code] ?? $code,
            'count' => $countryCounts[$code] ?? 0,
        ];
    }
    ksort($continentGroups);
    foreach ($continentGroups as &$countriesInGroup) {
        usort($countriesInGroup, fn ($a, $b) => $b['count'] <=> $a['count'] ?: $a['name'] <=> $b['name']);
    }
    unset($countriesInGroup);

    $countrySelectName = $filter->region !== null ? 'region' : 'country';

    $sortOptions = \App\Support\CatalogueFilter::SORT_LABELS;

    $languageOptions = collect($languages)
        ->map(fn (int $count, string $language) => ['language' => $language, 'count' => $count])
        ->values()
        ->sortBy([['count', 'desc'], ['language', 'asc']])
        ->all();

    $tokens = $filter->tokens();
    if (! $filter->alive) {
        $offlineQs = $filter->toQueryString(['alive' => null, 'page' => null]);
        $tokens[] = [
            'key' => 'alive',
            'label' => 'Including offline',
            'url' => route('radio.index').($offlineQs !== '' ? '?'.$offlineQs : ''),
        ];
    }
@endphp

<div class="bg-menu flex flex-col gap-2 px-4 py-3 max-[640px]:px-3"
     x-show="!uiHidden"
     x-data="{ filtersOpen: false }"
     x-on:keydown.escape.window="filtersOpen = false">
    <form method="GET" action="{{ route('radio.index') }}"
          class="flex flex-wrap items-center gap-3">
        @if ($filter->genre)
            <input type="hidden" name="genre" value="{{ $filter->genre }}">
        @endif

        {{-- Favourites toggle — at the left of the bar (§6.3). Just another param. --}}
        <label class="nc-field nc-toggle shrink-0">
            <input type="checkbox" name="fav" value="1"
                   @checked($filter->fav)
                   x-on:change="$el.form.requestSubmit()">
            Favourites
        </label>

        {{-- Search (§6.2). Enter submits immediately (native); typing auto-submits after 350ms idle. --}}
        <div class="max-w-[420px] min-w-[160px] flex-1">
            <label for="tune-search" class="sr-only">Find a station</label>
            <input type="search" id="tune-search" name="q" value="{{ $filter->q }}"
                   placeholder="Find a station"
                   class="nc-field"
                   x-on:input.debounce.350ms="$el.value.length === 0 || $el.value.length >= 2 ? $el.form.requestSubmit() : null">
        </div>

        {{-- Country / Language / Sort / On air only — inline on desktop, a slide-in
             drawer below 720px (reuses the existing slide-in mechanic's visual
             language; no new interaction pattern per §4.6). --}}
        <div x-bind:class="filtersOpen ? 'max-[720px]:!translate-x-0' : ''"
             x-on:keydown.tab="(event) => {
                 const focusables = $el.querySelectorAll('select, input, button, a');
                 if (! focusables.length) return;
                 const first = focusables[0];
                 const last = focusables[focusables.length - 1];
                 if (event.shiftKey && document.activeElement === first) {
                     event.preventDefault();
                     last.focus();
                 } else if (! event.shiftKey && document.activeElement === last) {
                     event.preventDefault();
                     first.focus();
                 }
             }"
             class="bg-menu flex flex-wrap items-center gap-3 max-[720px]:fixed max-[720px]:inset-0 max-[720px]:z-40 max-[720px]:translate-x-full max-[720px]:flex-col max-[720px]:items-stretch max-[720px]:gap-4 max-[720px]:overflow-y-auto max-[720px]:p-6 max-[720px]:pt-16 max-[720px]:transition-transform max-[720px]:duration-300 max-[720px]:ease-in-out">
            <button type="button"
                    aria-label="Close filters"
                    x-on:click="filtersOpen = false"
                    class="text-app-text hidden text-2xl leading-none outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-icon-hover max-[720px]:absolute max-[720px]:top-4 max-[720px]:right-4 max-[720px]:block">
                &times;
            </button>

            <div class="min-w-[160px]">
                <label for="tune-country" class="sr-only">Country</label>
                <select id="tune-country" name="{{ $countrySelectName }}"
                        class="nc-field"
                        x-on:change="$el.name = $el.selectedOptions[0].dataset.kind === 'region' ? 'region' : 'country'; $el.form.requestSubmit()">
                    <option value="" data-kind="none">All countries</option>
                    @foreach ($continentGroups as $continent => $countries)
                        <optgroup label="{{ ucfirst($continent) }}">
                            <option value="{{ $continent }}" data-kind="region"
                                    @selected($filter->region === $continent)>
                                All of {{ ucfirst($continent) }}
                            </option>
                            @foreach ($countries as $country)
                                <option value="{{ $country['code'] }}" data-kind="country"
                                        @selected($filter->country === $country['code'])>
                                    {{ $country['name'] }} ({{ number_format($country['count']) }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[160px]">
                <label for="tune-language" class="sr-only">Language</label>
                <select id="tune-language" name="language"
                        class="nc-field"
                        x-on:change="$el.form.requestSubmit()">
                    <option value="">All languages</option>
                    @foreach ($languageOptions as $option)
                        <option value="{{ $option['language'] }}" @selected($filter->language === $option['language'])>
                            {{ ucfirst($option['language']) }} ({{ number_format($option['count']) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[160px]">
                <label for="tune-sort" class="sr-only">Sort</label>
                <select id="tune-sort" name="sort"
                        class="nc-field"
                        x-on:change="$el.form.requestSubmit()">
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filter->sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- On air only — defaults ON; turning it off is an explicit opt-in (§6.3). --}}
            <input type="hidden" name="alive" value="0">
            <label class="nc-field nc-toggle shrink-0">
                <input type="checkbox" name="alive" value="1"
                       @checked($filter->alive)
                       x-on:change="$el.form.requestSubmit()">
                On air only
            </label>
        </div>

        <noscript>
            <button type="submit" class="nc-field">
                Apply
            </button>
        </noscript>

        {{-- "Filters" — opens the drawer above, below 720px only (§4.6). --}}
        <button type="button"
                x-on:click="filtersOpen = true"
                aria-haspopup="true"
                x-bind:aria-expanded="filtersOpen.toString()"
                class="nc-field">
            Filters
        </button>
    </form>

    <div x-show="filtersOpen" x-cloak style="display: none;"
         x-on:click="filtersOpen = false"
         class="fixed inset-0 z-30 bg-[color:color-mix(in_srgb,var(--color-app)_55%,transparent)]"></div>

    {{-- Active-filter tokens (§6.4) — always visible when any filter narrows the view. --}}
    @if ($filter->isActive())
        <div class="font-opensans flex flex-wrap items-center gap-2 text-xs">
            @foreach ($tokens as $token)
                <a href="{{ $token['url'] }}"
                   aria-label="Remove filter: {{ $token['label'] }}"
                   class="border-btn-border bg-btn hover:bg-btn-hover text-app-text inline-flex items-center gap-1 rounded-full border px-2.5 py-1 font-semibold no-underline outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-icon-hover">
                    <span aria-hidden="true">{{ $token['label'] }} &times;</span>
                </a>
            @endforeach

            <a href="{{ route('radio.index') }}"
               class="text-app-text hover:text-icon-hover font-semibold underline outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-icon-hover">
                Clear all
            </a>
        </div>
    @endif
</div>
