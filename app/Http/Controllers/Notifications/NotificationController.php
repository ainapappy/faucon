<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification history of the signed-in user (phase 10, D6).
 *
 * User-scoped BY CONSTRUCTION (A7): the resource is the user itself — the
 * scoping IS the policy, no team permission is involved.
 */
final class NotificationController extends Controller
{
    /**
     * Notifications per page for the history.
     */
    private const PerPage = 15;

    /**
     * List the user notifications, most recent first, with the read flag.
     */
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()
            ->paginate(self::PerPage)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => [
                ...NotificationPresenter::item($notification),
                // Present in the PAGE only — the bell (`recent`) carries
                // unread notifications exclusively (D10).
                'read' => $notification->read_at !== null,
            ]);

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
        ]);
    }
}
