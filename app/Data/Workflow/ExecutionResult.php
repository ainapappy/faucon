<?php

namespace App\Data\Workflow;

/**
 * Complete result of a run.
 *
 * status is 'completed', 'failed' or 'cancelled' — the latter since phase 7
 * (between-nodes cancellation): the runner stays pure, the queued job maps
 * the result onto the persisted ExecutionStatus transitions.
 */
final readonly class ExecutionResult
{
    /**
     * @param  list<NodeRunResult>  $nodes  ALL graph nodes, in execution order,
     *                                      never-executed ones listed as 'skipped' at the end.
     * @param  list<ExecutionError>  $errors  Validation failures (run not started) or the failing node error.
     */
    public function __construct(
        public string $status,
        public int $durationMs,
        public array $nodes,
        public array $errors = [],
    ) {}

    /**
     * @return array{status: string, durationMs: int, nodes: list<array<string, mixed>>, errors: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'durationMs' => $this->durationMs,
            'nodes' => array_map(fn (NodeRunResult $node): array => $node->toArray(), $this->nodes),
            'errors' => array_map(fn (ExecutionError $error): array => $error->toArray(), $this->errors),
        ];
    }
}
