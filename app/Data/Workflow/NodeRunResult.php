<?php

namespace App\Data\Workflow;

/**
 * Issue of ONE node within a run.
 */
final readonly class NodeRunResult
{
    /**
     * @param  string  $nodeKey  Client key of the node (UUID).
     * @param  string  $type  Node type id.
     * @param  string  $name  Node name as displayed in the run results.
     * @param  string  $status  'ok' | 'error' | 'skipped'.
     * @param  int  $durationMs  0 when skipped.
     * @param  array<string, mixed>  $output  [] when skipped.
     * @param  array<string, mixed>  $input  Aggregated input received by the node
     *                                       (sample input for the trigger, merged
     *                                       upstream outputs otherwise); [] when skipped.
     */
    public function __construct(
        public string $nodeKey,
        public string $type,
        public string $name,
        public string $status,
        public int $durationMs,
        public array $output,
        public ?ExecutionError $error = null,
        public array $input = [],
    ) {}

    /**
     * @return array{nodeKey: string, type: string, name: string, status: string, durationMs: int, output: array<string, mixed>, error: array<string, mixed>|null, input: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'nodeKey' => $this->nodeKey,
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'durationMs' => $this->durationMs,
            'output' => $this->output,
            'error' => $this->error?->toArray(),
            'input' => $this->input,
        ];
    }

    /**
     * Compact shape persisted in `workflow_executions.result` (phase 8 D2):
     * the payloads live only in the log rows, so `output` and `input` are
     * dropped.
     *
     * @return array{nodeKey: string, type: string, name: string, status: string, durationMs: int, error: array<string, mixed>|null}
     */
    public function toSummaryArray(): array
    {
        return [
            'nodeKey' => $this->nodeKey,
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'durationMs' => $this->durationMs,
            'error' => $this->error?->toArray(),
        ];
    }
}
