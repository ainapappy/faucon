<?php

namespace App\Notifications;

use App\Models\WorkflowExecution;
use Illuminate\Notifications\Notification;

/**
 * In-app failure notice for a persisted run (phase 10, D4).
 *
 * Database channel only, synchronous (one insert). The RECIPIENT is the
 * run's AUTHOR only (validated decision A1): runs without an author
 * (webhook, schedule — user_id null) are skipped silently by the listener.
 */
final class ExecutionFailedNotification extends Notification
{
    /**
     * Maximum length of the carried message (provider messages can be long;
     * the bell shows one line anyway).
     */
    private const MessageLimit = 200;

    public function __construct(private readonly WorkflowExecution $execution) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Payload WITHOUT sensitive data — the source (execution.input, result,
     * webhook payloads) is NEVER included. `teamSlug` lets the author deep
     * link to the execution from any team (A7).
     *
     * @return array{workflowId: int, workflowName: string, executionId: int, teamSlug: string, nodeKey: string|null, nodeType: string|null, reason: string, message: string}
     */
    public function toDatabase(object $notifiable): array
    {
        $error = $this->execution->error ?? [];

        return [
            'workflowId' => $this->execution->workflow_id,
            'workflowName' => $this->execution->workflow->name,
            'executionId' => $this->execution->id,
            'teamSlug' => $this->execution->team->slug,
            'nodeKey' => $error['nodeKey'] ?? null,
            'nodeType' => $error['type'] ?? null,
            'reason' => $error['reason'] ?? 'unknown',
            'message' => mb_substr((string) ($error['message'] ?? ''), 0, self::MessageLimit),
        ];
    }
}
