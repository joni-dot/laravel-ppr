<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [AcceptInvitationController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('invitation.show');

    Route::post('invitation', [AcceptInvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.store');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/projects.php';
require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
