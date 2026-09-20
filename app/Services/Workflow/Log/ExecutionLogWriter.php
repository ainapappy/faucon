<?php

namespace App\Services\Workflow\Log;

use App\Data\Workflow\NodeRunResult;
use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
use Carbon\CarbonInterface;

/**
 * Writes the journal rows of one execution (phase 8, D4).
 *
 * The ONLY place where a log row is written — and therefore the ONLY place
 * where the redaction (D6) and the per-column size cap apply. The runner
 * stays pure; the queued job calls this writer through the `onNodeResult`
 * hook and at the lifecycle transitions.
 *
 * Reference time of `offset_ms` is the START OF THE ATTEMPT (`started_at`
 * written by the job), never the row creation — the queue wait must not
 * appear in the journal's t.
 */
final class ExecutionLogWriter
{
    /**
     * The note replacing a JSON column beyond the configured byte cap.
     */
    private const string OmissionNote = 'Contenu omis : dépasse la taille maximale de journalisation.';

    /**
     * Create the writer — redaction is injected, bounds are read per call.
     */
    public function __construct(private readonly SecretRedactor $redactor) {}

    /**
     * Pre-insert the node rows of the attempt in status 'queued' (the
     * timeline is fully visible from the start, maquette steps queued).
     *
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     */
    public function openAttempt(WorkflowExecution $execution, int $attempt, array $nodes, CarbonInterface $startedAt): void
    {
        foreach ($nodes as $node) {
            WorkflowExecutionLog::query()->create([
                'workflow_execution_id' => $execution->getKey(),
                'attempt' => $attempt,
                'kind' => ExecutionLogKind::Node,
                'node_key' => (string) $node['key'],
                'node_type' => (string) $node['type'],
                'node_name' => (string) $node['name'],
                'status' => 'queued',
                'duration_ms' => null,
                'message' => null,
                'level' => ExecutionLogLevel::Info,
                'input' => null,
                'output' => null,
                'error' => null,
                'offset_ms' => 0,
            ]);
        }
    }

    /**
     * Record one executed node: upsert by (execution, attempt, node_key) —
     * the row pre-inserted by openAttempt is updated in place, so a double
     * write can never duplicate the row (unique index guard).
     */
    public function recordNode(WorkflowExecution $execution, NodeRunResult $node, int $attempt, CarbonInterface $startedAt): void
    {
        WorkflowExecutionLog::query()->updateOrCreate(
            [
                'workflow_execution_id' => $execution->getKey(),
                'attempt' => $attempt,
                'node_key' => $node->nodeKey,
            ],
            [
                'kind' => ExecutionLogKind::Node,
                'node_type' => $node->type,
                'node_name' => $node->name,
                'status' => $node->status,
                'duration_ms' => $node->durationMs,
                'message' => $node->error === null
                    ? ExecutionLogMessages::nodeCompleted($node->name, $node->durationMs)
                    : ExecutionLogMessages::nodeFailed($node->error->message),
                'level' => $node->error === null ? ExecutionLogLevel::Ok : ExecutionLogLevel::Error,
                'input' => $this->capped($this->redactor->redact($node->input)),
                'output' => $this->capped($this->redactor->redact($node->output)),
                'error' => $node->error === null ? null : $this->capped($this->redactor->redact($node->error->toArray())),
                'offset_ms' => $this->offsetFrom($startedAt),
            ],
        );
    }

    /**
     * Record a cycle-of-life event (started, retry, end): an event row
     * (node_key null) carrying the journal message.
     */
    public function recordEvent(WorkflowExecution $execution, string $message, ExecutionLogLevel $level, int $attempt, CarbonInterface $startedAt): void
    {
        WorkflowExecutionLog::query()->create([
            'workflow_execution_id' => $execution->getKey(),
            'attempt' => $attempt,
            'kind' => ExecutionLogKind::Event,
            'node_key' => null,
            'node_type' => null,
            'node_name' => null,
            'status' => null,
            'duration_ms' => null,
            'message' => $message,
            'level' => $level,
            'input' => null,
            'output' => null,
            'error' => null,
            'offset_ms' => $this->offsetFrom($startedAt),
        ]);
    }

    /**
     * The node rows still 'queued' at attempt close become 'skipped' — the
     * nodes the attempt never executed (cancel between nodes, retry…).
     */
    public function closeAttempt(WorkflowExecution $execution, int $attempt): void
    {
        WorkflowExecutionLog::query()
            ->where('workflow_execution_id', $execution->getKey())
            ->where('attempt', $attempt)
            ->where('kind', ExecutionLogKind::Node->value)
            ->where('status', 'queued')
            ->update(['status' => 'skipped']);
    }

    /**
     * Milliseconds elapsed since the attempt start — abs() because the
     * Carbon 3 diff is signed (phase 7 trap).
     */
    private function offsetFrom(CarbonInterface $startedAt): int
    {
        return abs((int) round($startedAt->diffInMilliseconds(now())));
    }

    /**
     * After redaction, a JSON column beyond the configured byte cap becomes
     * an omission marker holding the original byte size (D6).
     *
     * @param  mixed  $value  Redacted value, always array|null here.
     * @return array<string, mixed>|null
     */
    private function capped(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return [];
        }

        $encoded = json_encode($value);

        if ($encoded === false) {
            return [
                '_payload_bytes' => 0,
                '_note' => self::OmissionNote,
            ];
        }

        $maxBytes = (int) config('workflows.logs.max_json_bytes', 65536);

        if (strlen($encoded) > $maxBytes) {
            return [
                '_payload_bytes' => strlen($encoded),
                '_note' => self::OmissionNote,
            ];
        }

        return $value;
    }
}
