<?php

use App\Http\Controllers\Admin\DeactivateUserController;
use App\Http\Controllers\Admin\ReactivateUserController;
use App\Http\Controllers\Admin\ResendInvitationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])
        ->can('viewAny', User::class)
        ->name('users.index');

    Route::post('users', [UserController::class, 'store'])
        ->can('create', User::class)
        ->name('users.store');

    Route::patch('users/{user}', [UserController::class, 'update'])
        ->can('update', 'user')
        ->name('users.update');

    Route::post('users/{user}/deactivate', DeactivateUserController::class)
        ->can('deactivate', 'user')
        ->name('users.deactivate');

    Route::post('users/{user}/reactivate', ReactivateUserController::class)
        ->can('reactivate', 'user')
        ->name('users.reactivate');

    Route::post('users/{user}/invitation', ResendInvitationController::class)
        ->can('resendInvitation', 'user')
        ->middleware('throttle:6,1')
        ->name('users.invitation.resend');

    Route::get('settings', [SettingsController::class, 'edit'])
        ->can('manage', Setting::class)
        ->name('settings.edit');

    Route::put('settings', [SettingsController::class, 'update'])
        ->can('manage', Setting::class)
        ->name('settings.update');
});
