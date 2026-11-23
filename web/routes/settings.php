<?php

use App\Http\Controllers\Settings\FamilyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

// Family accounts: admins only.
Route::middleware(['auth', 'verified', 'can:admin'])->group(function () {
    Route::get('settings/family', [FamilyController::class, 'index'])->name('family.index');
    Route::post('settings/family', [FamilyController::class, 'store'])->name('family.store');
    Route::patch('settings/family/{user}/admin', [FamilyController::class, 'toggleAdmin'])->name('family.toggle-admin');
    Route::delete('settings/family/{user}', [FamilyController::class, 'destroy'])->name('family.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
