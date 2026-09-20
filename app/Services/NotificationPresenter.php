<?php

namespace App\Services;

use Illuminate\Notifications\DatabaseNotification;

/**
 * The single source of truth of the notification projection (phase 10, D5).
 *
 * `type` is a SHORT stable key — the front must never depend on the FQCN;
 * an unknown future notification renders as generic.
 */
final class NotificationPresenter
{
    /**
     * Project a stored notification to its camelCase front shape.
     *
     * @return array{id: string, type: string, data: array<string, mixed>|null, createdAt: string}
     */
    public static function item(DatabaseNotification $notification): array
    {
        return [
            'id' => (string) $notification->getKey(),
            'type' => match (class_basename($notification->type)) {
                'ExecutionFailedNotification' => 'execution_failed',
                default => 'generic',
            },
            'data' => $notification->data,
            'createdAt' => $notification->created_at->toIso8601String(),
        ];
    }
}
