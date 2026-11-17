<?php

use App\Http\Controllers\Api\DesktopConnectionController;
use Illuminate\Support\Facades\Route;

// The Mac app's "Move to cloud". Every call except the first proves the app's secret.
Route::prefix('desktop/connections')->middleware('throttle:30,1')->group(function () {
    Route::post('/', [DesktopConnectionController::class, 'store'])->middleware('throttle:5,1')->name('api.desktop.connections.store');
    Route::post('{code}/status', [DesktopConnectionController::class, 'status'])->name('api.desktop.connections.status');
    Route::post('{code}/import', [DesktopConnectionController::class, 'import'])->name('api.desktop.connections.import');
    Route::post('{code}/finish', [DesktopConnectionController::class, 'finish'])->name('api.desktop.connections.finish');
});
