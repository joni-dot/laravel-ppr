<?php

use App\Http\Controllers\MarkAllNotificationsReadController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// A notification is only ever found among the signed-in person's own (App\Models\Notification::resolveRouteBinding).
Route::middleware('auth')
    ->prefix('notifications')
    ->name('notifications.')
    ->whereUuid('notification')
    ->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');

        Route::post('read-all', MarkAllNotificationsReadController::class)->name('read-all');

        Route::get('{notification}', [NotificationController::class, 'show'])
            ->can('view', 'notification')
            ->name('show');

        Route::patch('{notification}', [NotificationController::class, 'update'])
            ->can('update', 'notification')
            ->name('update');
    });
