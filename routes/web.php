<?php

use App\Http\Controllers\CallSessionController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RelayStatusController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public showcase: proves the Node relay is alive next to the PHP app.
Route::get('relay-status', [RelayStatusController::class, 'show'])->name('relay-status');
Route::get('relay-status.json', [RelayStatusController::class, 'data'])->name('relay-status.data');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('characters', CharacterController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('characters/{character}/retry', [CharacterController::class, 'retry'])->name('characters.retry');

    Route::post('characters/{character}/call-sessions', [CallSessionController::class, 'store'])->name('characters.call-sessions.store');
    Route::get('call-sessions/{callSession}', [CallSessionController::class, 'show'])->name('call-sessions.show');
});

require __DIR__.'/settings.php';
