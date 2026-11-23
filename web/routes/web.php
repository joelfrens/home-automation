<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\School\ChildController;
use App\Http\Controllers\School\SchoolController;
use Illuminate\Support\Facades\Route;

// No public landing page: go straight to the dashboard (login first if not signed in)
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // School & family reminders (logic in packages/reminders)
    Route::prefix('school')->name('school.')->controller(SchoolController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('new', 'create')->name('create');
        Route::post('read', 'read')->middleware('throttle:10,1')->name('read');   // AI call: limit cost
        Route::get('review', 'review')->name('review');
        Route::post('review', 'storeReviewed')->name('review.store');
        Route::delete('review', 'cancelReview')->name('review.cancel');
        Route::post('events', 'store')->name('events.store');
        Route::get('events/{event}/edit', 'edit')->name('events.edit');
        Route::put('events/{event}', 'update')->name('events.update');
        Route::delete('events/{event}', 'destroy')->name('events.destroy');
        Route::get('events/{event}/source', 'source')->name('events.source');
    });
    Route::post('school/children', [ChildController::class, 'store'])->name('school.children.store');
    Route::delete('school/children/{child}', [ChildController::class, 'destroy'])->name('school.children.destroy');
});

require __DIR__.'/settings.php';
