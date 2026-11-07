<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const int NOTIFICATIONS_PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $notifications = Notification::query()
            ->visibleTo($request->user())
            ->with('project:id,name')
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::NOTIFICATIONS_PER_PAGE)
            ->through(fn (Notification $notification) => [
                'id' => $notification->id,
                'kind' => $notification->type,
                'projectName' => $notification->project->name,
                'packageNumber' => $notification->data['package_number'],
                'packageTitle' => $notification->data['package_title'],
                'params' => (object) $notification->data['params'],
                'isRead' => $notification->read_at !== null,
                'createdAt' => $notification->created_at?->toIso8601String(),
            ]);

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Opening a notification marks it as read and goes to the package it is about.
     */
    public function show(Notification $notification): RedirectResponse
    {
        $notification->markAsRead();

        return to_route('projects.packages.show', [
            'project' => $notification->project_id,
            'package' => $notification->data['package_id'],
        ]);
    }

    /**
     * Marks the notification as read.
     */
    public function update(Notification $notification): RedirectResponse
    {
        $notification->markAsRead();

        return back();
    }
}
