<?php

use App\Http\Controllers\Api\DesktopConnectionController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Connecting the Mac app to an account. Every call except the first proves the app's secret.
Route::prefix('desktop/connections')->middleware('throttle:30,1')->group(function () {
    Route::post('/', [DesktopConnectionController::class, 'store'])->middleware('throttle:5,1')->name('api.desktop.connections.store');
    Route::post('{code}/status', [DesktopConnectionController::class, 'status'])->name('api.desktop.connections.status');
    Route::post('{code}/finish', [DesktopConnectionController::class, 'finish'])->name('api.desktop.connections.finish');
});

// The Mac app's sync, with the token it got when its connection finished.
Route::prefix('desktop/sync')->middleware('throttle:60,1')->group(function () {
    Route::post('/', [SyncController::class, 'sync'])->name('api.desktop.sync');
    Route::delete('/', [SyncController::class, 'disconnect'])->name('api.desktop.sync.disconnect');
});
