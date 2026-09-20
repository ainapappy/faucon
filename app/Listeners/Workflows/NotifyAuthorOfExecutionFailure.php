<?php

namespace App\Listeners\Workflows;

use App\Events\Workflows\WorkflowExecutionFailed;
use App\Notifications\ExecutionFailedNotification;
use Throwable;

/**
 * Notify the run's AUTHOR that his run definitively failed (phase 10, A1).
 *
 * Runs without an author (webhook, schedule — user_id null) notify NOBODY:
 * validated silent skip (limitation — Future Ideas: extend to the team).
 *
 * Best-effort BY DESIGN: a notification failure is reported and swallowed —
 * it must never fail a job that has just finished writing its final status
 * (the event is dispatched from the worker; an escaping exception would
 * retry the job and double-notify).
 */
final class NotifyAuthorOfExecutionFailure
{
    /**
     * Handle the event.
     */
    public function handle(WorkflowExecutionFailed $event): void
    {
        try {
            $event->execution->user?->notify(new ExecutionFailedNotification($event->execution));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
