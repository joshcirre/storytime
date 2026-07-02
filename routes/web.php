<?php

use App\Http\Controllers\CallSessionController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('characters', CharacterController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('characters/{character}/retry', [CharacterController::class, 'retry'])->name('characters.retry');

    Route::post('characters/{character}/call-sessions', [CallSessionController::class, 'store'])->name('characters.call-sessions.store');
    Route::get('call-sessions/{callSession}', [CallSessionController::class, 'show'])->name('call-sessions.show');
});

require __DIR__.'/settings.php';
