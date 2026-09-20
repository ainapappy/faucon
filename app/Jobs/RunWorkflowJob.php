<?php

namespace App\Jobs;

use App\Data\Workflow\ExecutionResult;
use App\Data\Workflow\NodeRunResult;
use App\Enums\ExecutionLogLevel;
use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Enums\NodeCategory;
use App\Enums\WorkflowStatus;
use App\Events\Workflows\WorkflowExecutionCompleted;
use App\Events\Workflows\WorkflowExecutionFailed;
use App\Events\Workflows\WorkflowExecutionStarted;
use App\Models\WorkflowExecution;
use App\Services\Workflow\ExecutionCancel;
use App\Services\Workflow\Log\ExecutionLogMessages;
use App\Services\Workflow\Log\ExecutionLogWriter;
use App\Services\Workflow\NodeCatalog;
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
    public function handle(WorkflowRunner $runner, WorkflowGraphMapper $mapper, ExecutionLogWriter $logs): void
    {
        $execution = WorkflowExecution::query()->find($this->executionId);

        if ($execution === null || $execution->isFinal()) {
            return;
        }

        if (ExecutionCancel::requested($this->executionId)) {
            $this->persistCancelled($execution, $logs);

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
                $logs,
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

        // Phase 8: the journal opens with the attempt-start line, then the
        // timeline rows appear in status queued, before any node executes.
        $logs->recordEvent(
            $execution,
            ExecutionLogMessages::started($this->triggerLabel($nodes, $execution->triggered_by)),
            ExecutionLogLevel::Info,
            $this->attempts(),
            $startedAt,
        );
        $logs->openAttempt($execution, $this->attempts(), $nodes, $startedAt);

        $result = $runner->run(
            $nodes,
            $edges,
            $execution->input ?? [],
            beforeNode: fn (string $nodeKey): bool => ExecutionCancel::requested($this->executionId),
            timeoutMs: (int) config('workflows.execution.timeout_ms', 120000),
            onNodeResult: function (NodeRunResult $nodeRun) use ($logs, $execution, $startedAt): void {
                $logs->recordNode($execution, $nodeRun, $this->attempts(), $startedAt);
            },
        );

        $this->persistResult($execution, $result, $startedAt, $logs);
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

        $writer = app(ExecutionLogWriter::class);
        $message = __('Une erreur interne a interrompu l’exécution.');

        $updated = $this->guardedUpdate($execution, [
            'status' => ExecutionStatus::Failed,
            'error' => [
                'nodeKey' => null,
                'type' => 'internal',
                'reason' => 'job_failed',
                'message' => $message,
            ],
            'finished_at' => now(),
        ]);

        if ($updated > 0) {
            $writer->closeAttempt($execution, max(1, (int) $execution->attempt));
            $writer->recordEvent(
                $execution,
                ExecutionLogMessages::failed($message),
                ExecutionLogLevel::Error,
                max(1, (int) $execution->attempt),
                now(),
            );

            WorkflowExecutionFailed::dispatch($execution->fresh() ?? $execution);
        }
    }

    /**
     * Map a runner result onto the persisted transitions (D1).
     */
    private function persistResult(WorkflowExecution $execution, ExecutionResult $result, CarbonImmutable $startedAt, ExecutionLogWriter $logs): void
    {
        $finishedAt = now();
        $durationMs = abs((int) round($startedAt->diffInMilliseconds($finishedAt)));

        if ($result->status === 'cancelled') {
            ExecutionCancel::clear($this->executionId);

            $this->guardedUpdate($execution, [
                'status' => ExecutionStatus::Cancelled,
                // Partial result, summary only since phase 8: the payloads
                // live in the log rows.
                'result' => $result->toSummaryArray(),
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
            ]);

            $logs->closeAttempt($execution, $this->attempts());
            $logs->recordEvent(
                $execution,
                ExecutionLogMessages::cancelled(),
                ExecutionLogLevel::Info,
                $this->attempts(),
                $startedAt,
            );

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
                'result' => $result->toSummaryArray(),
                'error' => null,
                'finished_at' => $finishedAt,
                'duration_ms' => $durationMs,
            ]);

            $logs->closeAttempt($execution, $this->attempts());
            $logs->recordEvent(
                $execution,
                ExecutionLogMessages::completed(),
                ExecutionLogLevel::Ok,
                $this->attempts(),
                $startedAt,
            );

            WorkflowExecutionCompleted::dispatch($execution);

            return;
        }

        if (RetryPolicy::isRetryable($result) && $this->attempts() < $this->tries()) {
            $logs->closeAttempt($execution, $this->attempts());
            $logs->recordEvent(
                $execution,
                ExecutionLogMessages::retryScheduled($this->attempts() + 1, $this->tries(), $this->delayForNextAttempt()),
                ExecutionLogLevel::Info,
                $this->attempts(),
                $startedAt,
            );

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
            'result' => $result->toSummaryArray(),
            'error' => $errorPayload,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
        ]);

        $logs->closeAttempt($execution, $this->attempts());
        $logs->recordEvent(
            $execution,
            ExecutionLogMessages::failed($result->errors !== [] ? $result->errors[0]->message : null),
            ExecutionLogLevel::Error,
            $this->attempts(),
            $startedAt,
        );

        WorkflowExecutionFailed::dispatch($execution);
    }

    /**
     * Persist the cancelled status (flag honored at the start of a run) and
     * drop the flag. Guarded: a final state is never overwritten.
     */
    private function persistCancelled(WorkflowExecution $execution, ExecutionLogWriter $logs): void
    {
        $updated = $this->guardedUpdate($execution, [
            'status' => ExecutionStatus::Cancelled,
            'finished_at' => now(),
        ]);

        if ($updated > 0) {
            // Offset 0: the run never started, there is no attempt start.
            $logs->recordEvent($execution, ExecutionLogMessages::cancelled(), ExecutionLogLevel::Info, max(1, (int) $execution->attempt), now());
            ExecutionCancel::clear($this->executionId);
        }
    }

    /**
     * Persist a failed status from an explicit reason — guarded the same
     * way, and dispatching the failure event only on an effective write.
     */
    private function persistFailed(WorkflowExecution $execution, ?string $nodeKey, string $type, string $reason, string $message, ExecutionLogWriter $logs): void
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
            $logs->recordEvent($execution, ExecutionLogMessages::failed($message), ExecutionLogLevel::Error, max(1, (int) $execution->attempt), now());
            WorkflowExecutionFailed::dispatch($execution->fresh() ?? $execution);
        }
    }

    /**
     * The journal label of the run trigger (A8-5): never the webhook path
     * or id (public secret). The schedule cron is read in the config of the
     * mapped trigger node.
     *
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     */
    private function triggerLabel(array $nodes, ExecutionTrigger $trigger): string
    {
        if ($trigger === ExecutionTrigger::Schedule) {
            $cron = '';

            foreach ($nodes as $node) {
                if (NodeCatalog::categoryFor((string) $node['type']) === NodeCategory::Trigger) {
                    $cron = (string) ($node['config']['cron'] ?? '');

                    break;
                }
            }

            return __('Planifié — cron :cron', ['cron' => $cron]);
        }

        return $trigger->value === ExecutionTrigger::Webhook->value ? __('Webhook') : __('Manuel');
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
