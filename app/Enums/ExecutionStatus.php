<?php

namespace App\Enums;

/**
 * Lifecycle of a persisted workflow execution (phase 7).
 *
 * pending = waiting for a job attempt (initial dispatch or retry backoff).
 * running = a job attempt is executing. completed, failed and cancelled are
 * the three final states. cancelled is decided by the cancellation flag
 * checked by the job (start of attempt or between nodes) — an HTTP endpoint
 * only sets the flag, it never writes the status directly.
 */
enum ExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
