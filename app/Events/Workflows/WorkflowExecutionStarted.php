<?php

namespace App\Events\Workflows;

use App\Models\WorkflowExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The execution entered the running state (phase 7).
 *
 * Dispatched by RunWorkflowJob on the persisted transition to running.
 * Intermediate re-pending (retry backoff) and cancelled dispatch nothing
 * for now — the phase 8 listeners will say whether they need them.
 */
final class WorkflowExecutionStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WorkflowExecution $execution) {}
}
