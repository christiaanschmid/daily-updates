<?php

use App\Livewire\Updates\Huddle;
use App\Livewire\Updates\Index;
use App\Livewire\Updates\Show;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Index::class)->name('dashboard');

    Route::livewire('huddle', Huddle::class)->name('huddle');

    Route::livewire('updates/{date}', Show::class)
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('updates.show');
});

require __DIR__.'/settings.php';
