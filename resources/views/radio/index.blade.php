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
        document.documentElement.dataset.bootTheme = localStorage.getItem('theme') ?? 'blue';
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-app text-app-text m-0 min-h-screen w-full font-sans"
      data-theme="blue"
      x-data="radioPlayer"
      x-init="boot()"
      @keydown.window="onKeydown($event)">
    <script>
        document.body.dataset.theme = document.documentElement.dataset.bootTheme;
        window.RADIO_STATIONS = @json($radios);
    </script>

    @include('radio.partials.background')
    @include('radio.partials.player')
    @include('radio.partials.radio-list')
    @include('radio.partials.settings')
    @include('radio.partials.footer')
</body>
</html>
