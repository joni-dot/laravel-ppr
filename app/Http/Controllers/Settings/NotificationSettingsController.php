<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Notifications', [
            'notificationDays' => $request->user()->notification_days,
            'maxNotificationDays' => NotificationSettingsUpdateRequest::MAX_DAYS,
        ]);
    }

    public function update(NotificationSettingsUpdateRequest $request): RedirectResponse
    {
        $request->user()->notification_days = $request->integer('notification_days');
        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return to_route('notification-settings.edit');
    }
}
