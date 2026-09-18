<?php

use App\Http\Controllers\BackgroundController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\NowPlayingController;
use App\Http\Controllers\RadioController;
use App\Http\Controllers\StationHealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RadioController::class, 'index'])->name('radio.index');
Route::get('/api/radios', [RadioController::class, 'list'])->name('radio.list');
Route::get('/api/background', BackgroundController::class)->name('radio.background');

Route::get('/api/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/api/catalogue/random', [CatalogueController::class, 'random'])->name('catalogue.random');

// The route's `missing()` binding, rather than the default 404 that implicit
// model binding would otherwise throw, so an unknown id still returns 204 —
// this is telemetry, and a probing client should learn nothing from the
// response.
Route::post('/api/stations/{radio}/unreachable', [StationHealthController::class, 'unreachable'])
    ->name('stations.unreachable')
    ->middleware('throttle:20,1')
    ->missing(fn () => response()->noContent());

Route::post('/api/stations/{radio}/ok', [StationHealthController::class, 'ok'])
    ->name('stations.ok')
    ->middleware('throttle:20,1')
    ->missing(fn () => response()->noContent());

// Same `missing()` telemetry pattern as above: an unknown station id gets a
// bare 204 rather than a 404, so a probing client learns nothing. This one
// is a pure cache read (§6.5 of the redesign direction) — see
// NowPlayingController for why it never blocks on the network.
Route::get('/api/stations/{radio}/now-playing', [NowPlayingController::class, 'show'])
    ->name('stations.now-playing')
    ->middleware('throttle:20,1')
    ->missing(fn () => response()->noContent());
