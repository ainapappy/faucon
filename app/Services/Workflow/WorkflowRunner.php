<?php

namespace App\Services\Workflow;

use App\Data\Workflow\ExecutionError;
use App\Data\Workflow\ExecutionResult;
use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeRunResult;
use App\Enums\NodeCategory;
use App\Services\Workflow\Exception\NodeExecutionException;
use Throwable;

/**
 * Orchestrates a full run: validate → traverse → execute → report.
 *
 * Knows no node type: branching, terminality and aliasing are carried by the
 * NodeResult flags (branch/isTerminal/exposeAs) announced by the handlers.
 * Pure and in-memory: since phase 7 it is called by RunWorkflowJob (queued,
 * with a cancellation hook and a per-run budget) as well as by the
 * synchronous test-run, without any HTTP dependency.
 */
final class WorkflowRunner
{
    public function __construct(
        private readonly WorkflowValidator $validator,
        private readonly NodeHandlerRegistry $registry,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * Run a graph with a sample input.
     *
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     * @param  list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>  $edges
     * @param  array<string, mixed>  $sampleInput  Input of the trigger node.
     * @param  (callable(string): bool)|null  $beforeNode  Called before each node; returning
     *                                                     true stops the run between nodes
     *                                                     (queued cancellation, phase 7).
     * @param  int|null  $timeoutMs  Override of the constructor budget for one run — the
     *                               queued job passes the async budget, the synchronous
     *                               test-run keeps the constructor default.
     * @param  (callable(NodeRunResult): void)|null  $onNodeResult  Called after EVERY executed
     *                                                              node (ok AND error, including
     *                                                              NodeExecutionException/Throwable),
     *                                                              never for the skipped ones
     *                                                              (phase 8 — the queued job
     *                                                              records log rows). The runner
     *                                                              stays pure: it knows neither
     *                                                              the database nor the redaction.
     * @return ExecutionResult Status is 'completed', 'failed' or 'cancelled' (the runner
     *                         never persists anything; the job maps the status).
     */
    public function run(array $nodes, array $edges, array $sampleInput, ?callable $beforeNode = null, ?int $timeoutMs = null, ?callable $onNodeResult = null): ExecutionResult
    {
        $startedAt = hrtime(true);
        $budgetMs = $timeoutMs ?? $this->timeoutMs;

        $validationErrors = $this->validator->validate($nodes, $edges);

        if ($validationErrors !== []) {
            return new ExecutionResult(
                status: 'failed',
                durationMs: $this->elapsedMs($startedAt),
                nodes: [],
                errors: $validationErrors,
            );
        }

        $traverser = new GraphTraverser($nodes, $edges);
        $execution = new ExecutionContext;
        $executedOutputs = [];
        $queue = [];
        $seen = [];
        $runErrors = [];
        $results = [];

        $triggerKey = null;

        foreach ($nodes as $node) {
            if (NodeCatalog::categoryFor((string) $node['type']) === NodeCategory::Trigger) {
                $triggerKey = (string) $node['key'];
                break;
            }
        }

        $seen[$triggerKey] = true;
        $queue = [$triggerKey];
        $cancelled = false;

        while ($queue !== []) {
            $key = array_shift($queue);

            if ($beforeNode !== null && $beforeNode($key)) {
                $cancelled = true;

                break;
            }

            if ($this->elapsedMs($startedAt) >= $budgetMs) {
                $runErrors[] = new ExecutionError(
                    nodeKey: null,
                    type: 'validation',
                    reason: 'timeout',
                    message: __('L’exécution a dépassé la durée maximale de :seconds s.', ['seconds' => (int) ceil($budgetMs / 1000)]),
                );

                break;
            }

            $node = $traverser->node($key);

            if ($node === null) {
                continue;
            }

            $isTrigger = $key === $triggerKey;
            $input = $isTrigger
                ? $sampleInput
                : $traverser->mergeInputs($key, $executedOutputs);

            $context = new NodeContext(
                nodeKey: $key,
                nodeType: (string) $node['type'],
                nodeName: (string) $node['name'],
                config: $node['config'],
                input: $input,
                execution: $execution,
            );

            $nodeStartedAt = hrtime(true);

            try {
                $handler = $this->registry->forType((string) $node['type']);

                $result = $handler->execute($context);

                $durationMs = $this->elapsedMs($nodeStartedAt);

                $executedOutputs[$key] = $result->output;
                $execution->setNodeOutput($key, $result->output);

                if ($isTrigger) {
                    $execution->setVariable('trigger', $result->output);
                }

                if ($result->exposeAs !== null) {
                    $execution->setVariable($result->exposeAs, $result->output);
                }

                $nodeRun = new NodeRunResult(
                    nodeKey: $key,
                    type: (string) $node['type'],
                    name: (string) $node['name'],
                    status: 'ok',
                    durationMs: $durationMs,
                    output: $result->output,
                    input: $input,
                );

                $results[] = $nodeRun;

                if ($onNodeResult !== null) {
                    $onNodeResult($nodeRun);
                }

                if ($result->isTerminal) {
                    continue;
                }

                foreach ($traverser->successorsOf($key, $result->branch) as $successor) {
                    if (! isset($seen[$successor])) {
                        $seen[$successor] = true;
                        $queue[] = $successor;
                    }
                }

            } catch (NodeExecutionException $exception) {
                report($exception);

                $error = new ExecutionError(
                    nodeKey: $key,
                    type: (string) $node['type'],
                    reason: $exception->reason,
                    message: $exception->userMessage,
                );

                $nodeRun = new NodeRunResult(
                    nodeKey: $key,
                    type: (string) $node['type'],
                    name: (string) $node['name'],
                    status: 'error',
                    durationMs: $this->elapsedMs($nodeStartedAt),
                    output: [],
                    error: $error,
                    input: $input,
                );

                $results[] = $nodeRun;

                if ($onNodeResult !== null) {
                    $onNodeResult($nodeRun);
                }

                $runErrors[] = $error;

                break;
            } catch (Throwable $exception) {
                report($exception);

                $error = new ExecutionError(
                    nodeKey: $key,
                    type: (string) $node['type'],
                    reason: 'exception',
                    message: __('Une erreur interne est survenue dans le node « :name ».', ['name' => $node['name']]),
                );

                $runErrors[] = $error;

                $nodeRun = new NodeRunResult(
                    nodeKey: $key,
                    type: (string) $node['type'],
                    name: (string) $node['name'],
                    status: 'error',
                    durationMs: $this->elapsedMs($nodeStartedAt),
                    output: [],
                    error: $error,
                    input: $input,
                );

                $results[] = $nodeRun;

                if ($onNodeResult !== null) {
                    $onNodeResult($nodeRun);
                }

                break;
            }
        }

        foreach ($nodes as $node) {
            if (! isset($executedOutputs[$node['key']])) {
                $results[] = new NodeRunResult(
                    nodeKey: (string) $node['key'],
                    type: (string) $node['type'],
                    name: (string) $node['name'],
                    status: 'skipped',
                    durationMs: 0,
                    output: [],
                );
            }
        }

        $status = match (true) {
            $cancelled => 'cancelled',
            $runErrors !== [] => 'failed',
            default => 'completed',
        };

        return new ExecutionResult(
            status: $status,
            durationMs: $this->elapsedMs($startedAt),
            nodes: $results,
            errors: $runErrors,
        );
    }

    /**
     * Milliseconds elapsed since the given hrtime start, as an int.
     */
    private function elapsedMs(int $startedAt): int
    {
        return (int) round(((hrtime(true) - $startedAt) / 1e6));
    }
}
