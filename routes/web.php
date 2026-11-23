<?php

use App\Http\Controllers\Desktop\OpenExternalLinkController;
use App\Livewire\Desktop\Connect;
use App\Livewire\Updates\Huddle;
use App\Livewire\Updates\Index;
use App\Livewire\Updates\Show;
use App\Support\Desktop;
use Illuminate\Support\Facades\Route;

// Guests on the web see the home page. Signed-in users, and the Mac app, which has no
// need for one, go straight to their updates.
Route::get('/', function () {
    if (Desktop::running() || auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

// Paddle's default payment link: it opens checkouts for payments and payment method updates.
Route::view('pay', 'pay')->name('pay');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Index::class)->name('dashboard');

    // Today's page, wherever "today" is when it's opened, such as from the Mac app's Go menu.
    Route::get('today', fn () => redirect()->route('updates.show', today()->format('Y-m-d')))->name('today');

    Route::livewire('huddle', Huddle::class)->name('huddle');

    Route::livewire('updates/{date}', Show::class)
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('updates.show');

    // Where the Mac app sends the user to approve moving its logs to the cloud.
    Route::livewire('desktop/connect/{code}', Connect::class)->name('desktop.connect');
});

// Mac app: links to other sites open in the default browser.
Route::post('desktop/open-external', OpenExternalLinkController::class)
    ->middleware('auth')
    ->name('desktop.open-external');

require __DIR__.'/settings.php';
