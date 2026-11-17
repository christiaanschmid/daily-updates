<?php

use App\Http\Controllers\DesktopLoginController;
use App\Livewire\Desktop\Connect;
use App\Livewire\Updates\Huddle;
use App\Livewire\Updates\Index;
use App\Livewire\Updates\Show;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Paddle's default payment link: it opens checkouts for payments and payment method updates.
Route::view('pay', 'pay')->name('pay');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Index::class)->name('dashboard');

    Route::livewire('huddle', Huddle::class)->name('huddle');

    Route::livewire('updates/{date}', Show::class)
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('updates.show');

    // Where the Mac app sends the user to approve moving its logs to the cloud.
    Route::livewire('desktop/connect/{code}', Connect::class)->name('desktop.connect');
});

// The one-time link that signs the Mac app's window in after the move.
Route::get('desktop/login/{connection}', DesktopLoginController::class)
    ->middleware(['signed', 'throttle:10,1'])
    ->name('desktop.login');

require __DIR__.'/settings.php';
