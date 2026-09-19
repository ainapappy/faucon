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
     */
    public function __construct(
        public string $nodeKey,
        public string $type,
        public string $name,
        public string $status,
        public int $durationMs,
        public array $output,
        public ?ExecutionError $error = null,
    ) {}

    /**
     * @return array{nodeKey: string, type: string, name: string, status: string, durationMs: int, output: array<string, mixed>, error: array<string, mixed>|null}
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
        ];
    }
}
