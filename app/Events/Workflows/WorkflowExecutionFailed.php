<?php

namespace App\Events\Workflows;

use App\Models\WorkflowExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The execution failed definitively (phase 7).
 *
 * Dispatched by RunWorkflowJob when a failed outcome is persisted (last
 * attempt or non-retryable) and by failed() when the job dies brutally —
 * once per execution, never on intermediate states.
 */
final class WorkflowExecutionFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WorkflowExecution $execution) {}
}
