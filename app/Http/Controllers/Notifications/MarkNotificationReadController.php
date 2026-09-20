<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mark ONE notification read (phase 10, D6/A8) — idempotent.
 *
 * Resolution by SCOPING, never a global route-model binding: a foreign uuid
 * is a 404 (the user cannot even learn it exists — authorization.md).
 */
final class MarkNotificationReadController extends Controller
{
    /**
     * Mark the notification read.
     */
    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $model = $request->user()->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $model->markAsRead();

        return back();
    }
}
