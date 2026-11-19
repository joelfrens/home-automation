<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// No public landing page: go straight to the dashboard (login first if not signed in)
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
