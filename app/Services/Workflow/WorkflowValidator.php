<?php

namespace App\Services\Workflow;

use App\Data\Workflow\ExecutionError;
use App\Enums\NodeCategory;

/**
 * Decides whether a graph is executable, without running it.
 *
 * Composes GraphValidator::hasCycle (unchanged since phase 3) and delegates
 * config checks to the registered handlers. A failed validation is a normal
 * RESULT (list of ExecutionError), never an exception.
 */
final class WorkflowValidator
{
    public function __construct(private readonly NodeHandlerRegistry $registry) {}

    /**
     * Validate the graph for execution.
     *
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     * @param  list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>  $edges
     * @return list<ExecutionError>
     */
    public function validate(array $nodes, array $edges): array
    {
        $errors = [];
        $this->validateTriggers($nodes, $errors);
        $this->validateNodes($nodes, $errors);
        $this->validateAcyclic($edges, $errors);

        return $errors;
    }

    /**
     * Exactly one trigger node is required.
     *
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     * @param  list<ExecutionError>  $errors
     */
    private function validateTriggers(array $nodes, array &$errors): void
    {
        $triggerTypes = NodeCatalog::typesForCategory(NodeCategory::Trigger);
        $triggers = array_values(array_filter(
            $nodes,
            fn (array $node): bool => in_array($node['type'], $triggerTypes, true),
        ));

        if (count($triggers) === 0) {
            $errors[] = new ExecutionError(
                nodeKey: null,
                type: 'validation',
                reason: 'no_trigger',
                message: 'Le workflow doit contenir exactement un node déclencheur.',
            );

            return;
        }

        if (count($triggers) > 1) {
            $errors[] = new ExecutionError(
                nodeKey: null,
                type: 'validation',
                reason: 'multiple_triggers',
                message: __('Le workflow ne doit contenir qu\'un seul node déclencheur (:count trouvés).', ['count' => count($triggers)]),
            );
        }
    }

    /**
     * Per-node checks: type in catalog, handler registered, config valid.
     *
     * @param  list<array{key: string, type: string, name: string, config: array<string, mixed>}>  $nodes
     * @param  list<ExecutionError>  $errors
     */
    private function validateNodes(array $nodes, array &$errors): void
    {
        foreach ($nodes as $node) {
            $type = (string) $node['type'];
            $name = (string) $node['name'];

            // The registry is the executability authority (D8): a registered
            // handler makes any type runnable, even outside the catalog. The
            // catalog only refines the reason when NO handler exists.
            $handler = $this->registry->forType($type);

            if ($handler === null) {
                $errors[] = new ExecutionError(
                    nodeKey: (string) $node['key'],
                    type: NodeCatalog::has($type) ? $type : 'validation',
                    reason: NodeCatalog::has($type) ? 'handler_missing' : 'unknown_type',
                    message: NodeCatalog::has($type)
                        ? __('Le node « :name » utilise un type pas encore exécutable (:type).', ['name' => $name, 'type' => $type])
                        : __('Le node « :name » utilise un type inconnu (:type).', ['name' => $name, 'type' => $type]),
                );

                continue;
            }

            $configErrors = $handler->validate($node['config']);

            if ($configErrors !== []) {
                $errors[] = new ExecutionError(
                    nodeKey: (string) $node['key'],
                    type: $type,
                    reason: 'invalid_config',
                    message: __('Erreur de configuration : :error', ['error' => $configErrors[0]]),
                );
            }
        }
    }

    /**
     * The graph must be acyclic (composition of GraphValidator, unchanged).
     *
     * @param  list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>  $edges
     * @param  list<ExecutionError>  $errors
     */
    private function validateAcyclic(array $edges, array &$errors): void
    {
        $graphEdges = array_map(fn (array $edge): array => [
            'source' => (string) $edge['sourceNodeKey'],
            'target' => (string) $edge['targetNodeKey'],
        ], $edges);

        if (GraphValidator::hasCycle($graphEdges)) {
            $errors[] = new ExecutionError(
                nodeKey: null,
                type: 'validation',
                reason: 'cycle',
                message: 'Le graphe contient un cycle.',
            );
        }
    }
}
