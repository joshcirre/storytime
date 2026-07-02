<?php

use App\Http\Controllers\Relay\RelaySessionController;
use App\Http\Controllers\Relay\RelayToolController;
use Illuminate\Support\Facades\Route;

Route::get('sessions/pending', [RelaySessionController::class, 'pending'])->name('sessions.pending');
Route::post('sessions/{runwaySessionId}/claim', [RelaySessionController::class, 'claim'])->name('sessions.claim');
Route::post('sessions/{runwaySessionId}/end', [RelaySessionController::class, 'end'])->name('sessions.end');

Route::post('tools/weather', [RelayToolController::class, 'weather'])->name('tools.weather');
Route::post('tools/joke', [RelayToolController::class, 'joke'])->name('tools.joke');
