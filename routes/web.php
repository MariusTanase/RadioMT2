<?php

use App\Http\Controllers\BackgroundController;
use App\Http\Controllers\RadioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RadioController::class, 'index'])->name('radio.index');
Route::get('/api/radios', [RadioController::class, 'list'])->name('radio.list');
Route::get('/api/background', BackgroundController::class)->name('radio.background');
