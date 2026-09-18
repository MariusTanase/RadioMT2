{{--
    Search is the primary act, so it is a full-width rule rather than a boxed
    field. The rest are text-weight selects: filters should read as a sentence
    you are adjusting, not a toolbar.
--}}
<form method="GET" action="{{ route('radio.index') }}">
    @if ($filter->genre) <input type="hidden" name="genre" value="{{ $filter->genre }}"> @endif

    <input class="search" type="search" name="q" value="{{ $filter->q }}"
           placeholder="Search {{ number_format($radios->total()) }} stations"
           aria-label="Search stations"
           x-on:search="$el.form.requestSubmit()">

    <div class="filters">
        <select class="sel" name="country" aria-label="Country" x-on:change="$el.form.requestSubmit()">
            <option value="">Everywhere</option>
            @foreach ($countries as $code => $count)
                <option value="{{ $code }}" @selected($filter->country === $code)>{{ $code }} &nbsp;{{ number_format($count) }}</option>
            @endforeach
        </select>

        <select class="sel" name="language" aria-label="Language" x-on:change="$el.form.requestSubmit()">
            <option value="">Any language</option>
            @foreach ($languages as $lang => $count)
                <option value="{{ $lang }}" @selected($filter->language === $lang)>{{ Str::title($lang) }} &nbsp;{{ number_format($count) }}</option>
            @endforeach
        </select>

        <select class="sel" name="sort" aria-label="Sort" x-on:change="$el.form.requestSubmit()">
            @foreach (\App\Support\CatalogueFilter::SORT_LABELS as $key => $label)
                <option value="{{ $key }}" @selected($filter->sort === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <label class="switch">
            <input type="hidden" name="alive" value="0">
            <input type="checkbox" name="alive" value="1" @checked($filter->alive) x-on:change="$el.form.requestSubmit()">
            On air
        </label>

        <label class="switch">
            <input type="checkbox" name="fav" value="1" @checked($filter->fav) x-on:change="$el.form.requestSubmit()">
            Favourites
        </label>
    </div>
</form>
