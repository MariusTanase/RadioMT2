<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>MT Radio</title>
    <meta name="description" content="A tuner for {{ number_format($radios->total()) }} radio stations.">
    <meta name="author" content="Marius Tanase">
    <meta name="theme-color" content="#0e0d0b">

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="radioPlayer" x-init="boot()" @keydown.window="onKeydown($event)">

@php
    $bootStations = $radios->getCollection()->map(fn ($s) => [
        'id' => $s->id, 'title' => $s->title, 'genre' => $s->genre,
        'image' => $s->image, 'url' => $s->url,
    ])->values();
@endphp
<script>window.RADIO_STATIONS = @json($bootStations);</script>

{{-- One audio element for the whole app, outside the swappable region so a
     filter or page change never re-creates it and playback survives. --}}
<audio x-ref="audio"
       x-bind:src="current?.url"
       preload="none"
       @play="isPlaying = true"
       @pause="isPlaying = false"
       x-on:error="isPlaying = false"></audio>

<div class="shell" x-data="catalogue" style="padding-bottom: calc(var(--dock-h) + 24px)">

    <header class="masthead">
        <a href="{{ route('radio.index') }}" class="wordmark" style="text-decoration:none">MT RADIO</a>
        <span class="masthead-count">{{ number_format($radios->total()) }} stations</span>
    </header>

    @include('radio.partials.tune-bar')
    @include('radio.partials.genre-band')

    <div id="catalogue">
        @include('radio.partials.catalogue')
    </div>
</div>

@include('radio.partials.dock')
@include('radio.partials.settings')

</body>
</html>
