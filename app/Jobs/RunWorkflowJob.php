<?php

namespace App\Jobs;

use App\Data\Workflow\ExecutionResult;
use App\Enums\ExecutionStatus;
use App\Enums\WorkflowStatus;
use App\Events\Workflows\WorkflowExecutionCompleted;
use App\Events\Workflows\WorkflowExecutionFailed;
use App\Events\Workflows\WorkflowExecutionStarted;
use App\Models\WorkflowExecution;
use App\Services\Workflow\ExecutionCancel;
use App\Services\Workflow\RetryPolicy;
use App\Services\Workflow\WorkflowGraphMapper;
use App\Services\Workflow\WorkflowRunner;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Executes one persisted workflow execution on the queue (phase 7).
 *
 * Encapsulates the phase 4 runner — it never duplicates it: it loads the
 * execution and the graph fresh from the database, maps them, runs the
 * engine with a cancellation hook and the async budget, then maps the
 * result onto the persisted status transitions (see the phase 7 plan D1).
 *
 * The graph is read at run time, not at dispatch time: an edit between the
 * dispatch and the run is picked up (no snapshot — documented limitation).
 */
final class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Worker kill timeout of ONE attempt (seconds), frozen at dispatch from
     * the config (tries/backoff are re-read at execution, this is not: it is
     * a serialized property). Sized to hold the runner budget plus margin
     * for the status writes.
     */
    public int $timeout;

    /**
     * Create a new job instance for one execution.
     */
    public function __construct(public readonly int $executionId)
    {
        $this->timeout = (int) ceil(config('workflows.execution.timeout_ms', 120000) / 1000) + 60;
    }

    /**
     * Total attempts (initial + retries) — re-read in the worker.
     */
    public function tries(): int
    {
        return max(1, (int) config('workflows.execution.max_tries', 2));
    }

    /**
     * Seconds to wait between attempts — re-read in the worker.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        $backoff = config('workflows.execution.backoff', [30]);

        if (! is_array($backoff) || $backoff === []) {
            return [30];
        }

        return array_values(array_map(intval(...), $backoff));
    }

    /**
     * Never run two attempts of the same execution in parallel.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("exec:{$this->executionId}"))
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Run the execution.
     */
    public function handle(WorkflowRunner $runner, WorkflowGraphMapper $mapper): void
    {
        $execution = WorkflowExecution::query()->find($this->executionId);

        if ($execution === null || $execution->isFinal()) {
            return;
        }

        if (ExecutionCancel::requested($this->executionId)) {
            $this->persistCancelled($execution);

            return;
        }

        $workflow = $execution->workflow()->with(['nodes', 'edges'])->first();

        if ($workflow === null || $workflow->status !== WorkflowStatus::Active) {
            $this->persistFailed(
                $execution,
                null,
                'validation',
                'workflow_inactive',
                __('Ce workflow n’est plus actif et ne peut pas être exécuté.'),
            );

            return;
        }

        $startedAt = now();

        $execution->update([
            'status' => ExecutionStatus::Running,
            'started_at' => $startedAt,
            'finished_at' => null,
            'duration_ms' => null,
            'attempt' => $this->attempts(),
        ]);

        WorkflowExecutionStarted::dispatch($execution);

        [$nodes, $edges] = $mapper->map($workflow);

        $result = $runner->run(
            $nodes,
            $edges,
            $execution->input ?? [],
            beforeNode: fn (string $nodeKey): bool => ExecutionCancel::requested($this->executionId),
            timeoutMs: (int) config('workflows.execution.timeout_ms', 120000),
        );

        $this->persistResult($execution, $result, $startedAt);
    }

    /**
     * The job died brutally (worker timeout, fatal): mark the execution
     * failed once, guarded so an already-final execution is never touched.
     */
    public function failed(Throwable $exception): void
    {
        $execution = WorkflowExecution::query()->find($this->executionId);

        if ($execution === null || $execution->isFinal()) {
            return;
        }

        $this->persistFailed(
            $execution,
            null,
            'internal',
            'job_failed',
            __('Une erreur interne a interrompu l’exécution.'),
        );
    }

    /**
     * Map a runner result onto the persisted transitions (D1).
     */
    private function persistResult(WorkflowExecution $execution, ExecutionResult $result, CarbonImmutable $startedAt): void
    {
        $finishedAt = now();
        $durationMs = abs((int) round($startedAt->diffInMilliseconds($finishedAt)));

        if ($result->status === 'cancelled') {
            ExecutionCancel::clear($this->executionId);

            $this->guardedUpdate($execution, [
                'status' => ExecutionStatus::Cancelled,
                // Partial result: the sheet timeline shows the progress
                // (executed nodes ok, the rest skipped).
                'result' => $result->toArray(),
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
            ]);

            return;
        }

        $errorPayload = null;

        if ($result->errors !== []) {
            $first = $result->errors[0];
            $errorPayload = [
                'nodeKey' => $first->nodeKey,
                'type' => $first->type,
                'reason' => $first->reason,
                'message' => $first->message,
            ];
        }

        if ($result->status === 'completed') {
            $execution->update([
                'status' => ExecutionStatus::Completed,
                'result' => $result->toArray(),
                'error' => null,
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
            ]);

            WorkflowExecutionCompleted::dispatch($execution);

            return;
        }

        if (RetryPolicy::isRetryable($result) && $this->attempts() < $this->tries()) {
            $execution->update([
                'status' => ExecutionStatus::Pending,
                'started_at' => null,
                'finished_at' => null,
                'duration_ms' => null,
            ]);

            $this->release($this->delayForNextAttempt());

            return;
        }

        $execution->update([
            'status' => ExecutionStatus::Failed,
            'result' => $result->toArray(),
            'error' => $errorPayload,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
        ]);

        WorkflowExecutionFailed::dispatch($execution);
    }

    /**
     * Persist the cancelled status (flag honored at the start of a run) and
     * drop the flag. Guarded: a final state is never overwritten.
     */
    private function persistCancelled(WorkflowExecution $execution): void
    {
        $updated = $this->guardedUpdate($execution, [
            'status' => ExecutionStatus::Cancelled,
            'finished_at' => now(),
        ]);

        if ($updated > 0) {
            ExecutionCancel::clear($this->executionId);
        }
    }

    /**
     * Persist a failed status from an explicit reason — guarded the same
     * way, and dispatching the failure event only on an effective write.
     */
    private function persistFailed(WorkflowExecution $execution, ?string $nodeKey, string $type, string $reason, string $message): void
    {
        $updated = $this->guardedUpdate($execution, [
            'status' => ExecutionStatus::Failed,
            'error' => [
                'nodeKey' => $nodeKey,
                'type' => $type,
                'reason' => $reason,
                'message' => $message,
            ],
            'finished_at' => now(),
        ]);

        if ($updated > 0) {
            WorkflowExecutionFailed::dispatch($execution->fresh() ?? $execution);
        }
    }

    /**
     * Update the execution only while it is not final (no state overwrite).
     *
     * @param  array<string, mixed>  $attributes
     * @return int Number of updated rows (0 = already final).
     */
    private function guardedUpdate(WorkflowExecution $execution, array $attributes): int
    {
        if ($attributes['status'] instanceof ExecutionStatus) {
            $attributes['status'] = $attributes['status']->value;
        }

        if (isset($attributes['error']) && is_array($attributes['error'])) {
            $attributes['error'] = json_encode($attributes['error']);
        }

        return WorkflowExecution::query()
            ->whereKey($execution->getKey())
            ->whereNotIn('status', [
                ExecutionStatus::Completed->value,
                ExecutionStatus::Failed->value,
                ExecutionStatus::Cancelled->value,
            ])
            ->update($attributes);
    }

    /**
     * Backoff seconds for the NEXT attempt (index follows the attempt that
     * just failed; the last value repeats).
     */
    private function delayForNextAttempt(): int
    {
        $backoff = $this->backoff();

        return (int) ($backoff[$this->attempts() - 1] ?? $backoff[count($backoff) - 1]);
    }
}
