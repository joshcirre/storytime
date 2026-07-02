<?php

use App\Http\Controllers\CallSessionController;
use App\Http\Controllers\CharacterController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('characters', CharacterController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('characters/{character}/retry', [CharacterController::class, 'retry'])->name('characters.retry');

    Route::post('characters/{character}/call-sessions', [CallSessionController::class, 'store'])->name('characters.call-sessions.store');
    Route::get('call-sessions/{callSession}', [CallSessionController::class, 'show'])->name('call-sessions.show');
});

require __DIR__.'/settings.php';
