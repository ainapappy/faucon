<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mark every notification of the signed-in user read (phase 10, D6) — one
 * mass update, nothing is loaded from the database.
 */
final class MarkAllNotificationsReadController extends Controller
{
    /**
     * Mark all unread notifications read.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
