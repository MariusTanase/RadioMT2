<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MT Radio</title>
    <meta name="description" content="Project of MariusTanase.com, website made for an easier selection of radios to listen in free time">
    <meta name="author" content="Marius Tanase">
    <meta name="keywords" content="radio, online radio, marius tanase, mariustanase.com, capital radio online, virgin radio, capital UK, heart uk, heart radio online, monte carlo, deep house radio online">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <script>
        // Applied before first paint so the chosen theme never flashes.
        let bootTheme = 'blue';
        try {
            bootTheme = localStorage.getItem('theme') ?? 'blue';
        } catch {
            // localStorage can throw in some privacy/incognito configurations;
            // fall back to the default theme rather than aborting the page.
        }
        document.documentElement.dataset.bootTheme = bootTheme;
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-app text-app-text m-0 min-h-screen w-full font-sans"
      data-theme="blue"
      x-data="radioPlayer"
      x-init="boot()"
      @keydown.window="onKeydown($event)">
    @php
        // Only what the player binds against. This is the initial page load's
        // equivalent of the #catalogue-stations island the fragment emits.
        $bootStations = $radios->getCollection()->map(fn ($station) => [
            'id' => $station->id,
            'title' => $station->title,
            'genre' => $station->genre,
            'image' => $station->image,
            'url' => $station->url,
        ])->values();
    @endphp

    <script>
        document.body.dataset.theme = document.documentElement.dataset.bootTheme;
        window.RADIO_STATIONS = @json($bootStations);
    </script>

    @include('radio.partials.background')

    {{--
        The Deck, the Tune bar and the audio element sit OUTSIDE #catalogue.
        That is what keeps playback alive across a filter or page change:
        catalogue.js only ever replaces the contents of #catalogue.
    --}}
    @include('radio.partials.deck')

    {{--
        The single audio element for the whole app. It lives here, outside
        #catalogue, so a filter or page change never re-creates it and playback
        survives navigation. isPlaying is driven only by the element's own
        events — play() rejects when autoplay is blocked or a stream is dead,
        and the transport must not claim to be playing in that case.
    --}}
    <audio x-ref="audio"
           x-bind:src="current?.url"
           preload="none"
           @play="isPlaying = true"
           @pause="isPlaying = false"
           x-on:error="isPlaying = false"></audio>

    <main x-data="catalogue" class="mx-auto w-full max-w-[1200px] px-4 max-[720px]:mb-24">
        @include('radio.partials.tune-bar')
        @include('radio.partials.genre-band')

        <div id="catalogue"
             x-bind:class="pending ? 'pointer-events-none opacity-45' : ''"
             class="transition-opacity duration-200">
            @include('radio.partials.catalogue')
        </div>
    </main>

    @include('radio.partials.settings')
    @include('radio.partials.footer')
</body>
</html>
