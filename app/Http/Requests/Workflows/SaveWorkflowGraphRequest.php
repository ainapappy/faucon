<?php

namespace App\Http\Requests\Workflows;

use App\Models\Integration;
use App\Models\Team;
use App\Services\Workflow\GraphValidator;
use App\Services\Workflow\NodeCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWorkflowGraphRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nodes' => ['present', 'array', 'max:100'],
            'nodes.*.key' => ['required', 'string', 'max:64', 'distinct'],
            'nodes.*.type' => ['required', 'string', 'max:64', Rule::in(array_keys(NodeCatalog::all()))],
            'nodes.*.name' => ['required', 'string', 'max:255'],
            'nodes.*.config' => ['present', 'array'],
            'nodes.*.config.*' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && mb_strlen($value) > 10000) {
                        $fail(__('The :attribute must not exceed 10000 characters.'));

                        return;
                    }

                    // Null is allowed: ConvertEmptyStringsToNull turns the
                    // empty-string defaults of the builder into null, which
                    // the handlers treat exactly like an absent field.
                    if (! is_scalar($value) && $value !== null) {
                        $fail(__('The :attribute must be a scalar value.'));
                    }
                },
            ],
            'nodes.*.positionX' => ['required', 'numeric', 'between:-100000,100000'],
            'nodes.*.positionY' => ['required', 'numeric', 'between:-100000,100000'],
            'edges' => ['present', 'array', 'max:200'],
            'edges.*.sourceNodeKey' => ['required', 'string', 'max:64'],
            'edges.*.targetNodeKey' => ['required', 'string', 'max:64', 'different:edges.*.sourceNodeKey'],
            'edges.*.sourceHandle' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Get the after-validation callables (cross-field rules).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $nodes = $this->graphNodes();
                $edges = $this->graphEdges();

                $nodesByKey = [];

                foreach ($nodes as $index => $node) {
                    $nodesByKey[(string) $node['key']] = ['index' => $index, 'type' => (string) $node['type']];
                }

                $this->validateEdgeEndpoints($validator, $edges, $nodesByKey);
                $this->validateEdgeHandles($validator, $edges, $nodesByKey);
                $this->validateUniqueTriplets($validator, $edges);
                $this->validateConfigWhitelist($validator, $nodes, $nodesByKey);
                $this->validateNoCycle($validator, $edges);
                $this->validateIntegrationReferences($validator, $nodes);
            },
        ];
    }

    /**
     * Every non-empty `integration_id` must reference an integration of
     * the current team — dangling ids stay accepted so the autosave is
     * never blocked after a deletion (D15). One single whereIn query.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function validateIntegrationReferences(Validator $validator, array $nodes): void
    {
        $referenced = [];

        foreach ($nodes as $index => $node) {
            foreach ((array) ($node['config'] ?? []) as $field => $value) {
                if ($field === 'integration_id' && is_string($value) && $value !== '') {
                    $referenced[$value][] = (int) $index;
                }
            }
        }

        if ($referenced === []) {
            return;
        }

        $team = $this->route('current_team');

        if (! $team instanceof Team) {
            return;
        }

        $owned = Integration::query()
            ->whereIn('id', array_keys($referenced))
            ->where('team_id', $team->id)
            ->pluck('id')
            ->all();

        foreach ($referenced as $id => $indexes) {
            // PHP casts numeric array keys to int: the id is compared as int.
            $id = (int) $id;

            if (in_array($id, $owned, true)) {
                continue;
            }

            if (! Integration::query()->whereKey($id)->exists()) {
                continue;
            }

            foreach ($indexes as $index) {
                $validator->errors()->add(
                    "nodes.{$index}.config.integration_id",
                    __('L’intégration référencée n’appartient pas à cette équipe.'),
                );
            }
        }
    }

    /**
     * Get the payload nodes, normalized to a list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function graphNodes(): array
    {
        return array_values((array) $this->input('nodes'));
    }

    /**
     * Get the payload edges, normalized to a list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function graphEdges(): array
    {
        return array_values((array) $this->input('edges'));
    }

    /**
     * Every edge endpoint must reference a node key of the payload.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<string, array{index: int, type: string}>  $nodesByKey
     */
    private function validateEdgeEndpoints(Validator $validator, array $edges, array $nodesByKey): void
    {
        foreach ($edges as $index => $edge) {
            $source = (string) $edge['sourceNodeKey'];
            $target = (string) $edge['targetNodeKey'];

            if (! isset($nodesByKey[$source])) {
                $validator->errors()->add(
                    "edges.{$index}.sourceNodeKey",
                    __('The edge references the unknown node key ":key".', ['key' => $source]),
                );
            }

            if (! isset($nodesByKey[$target]) && $target !== $source) {
                $validator->errors()->add(
                    "edges.{$index}.targetNodeKey",
                    __('The edge references the unknown node key ":key".', ['key' => $target]),
                );
            }
        }
    }

    /**
     * A source handle must be a declared output of the source node type.
     *
     * @param  array<int, array<string, mixed>>  $edges
     * @param  array<string, array{index: int, type: string}>  $nodesByKey
     */
    private function validateEdgeHandles(Validator $validator, array $edges, array $nodesByKey): void
    {
        foreach ($edges as $index => $edge) {
            $type = $nodesByKey[(string) $edge['sourceNodeKey']]['type'] ?? null;

            if (! is_string($type)) {
                continue;
            }

            $definition = NodeCatalog::definitionFor($type);

            if ($definition === null) {
                continue;
            }

            $outputs = array_column($definition->outputs, 'id');

            // A type with zero output ports (data.output) cannot start an
            // edge at all — even in the null-handle default form.
            if ($outputs === []) {
                $validator->errors()->add(
                    "edges.{$index}.sourceHandle",
                    __('The node type ":type" declares no output port.', ['type' => $type]),
                );

                continue;
            }

            if ($edge['sourceHandle'] === null) {
                continue;
            }

            if (! in_array((string) $edge['sourceHandle'], $outputs, true)) {
                $validator->errors()->add(
                    "edges.{$index}.sourceHandle",
                    __('The handle ":handle" is not an output of the source node type ":type".', ['handle' => $edge['sourceHandle'], 'type' => $type]),
                );
            }
        }
    }

    /**
     * The (source, target, handle) triple must be unique.
     *
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function validateUniqueTriplets(Validator $validator, array $edges): void
    {
        $seen = [];

        foreach ($edges as $index => $edge) {
            $triplet = $edge['sourceNodeKey'].'|'.$edge['targetNodeKey'].'|'.(string) $edge['sourceHandle'];

            if (isset($seen[$triplet])) {
                $validator->errors()->add(
                    "edges.{$index}.sourceNodeKey",
                    __('The same connection is defined more than once.'),
                );

                continue;
            }

            $seen[$triplet] = true;
        }
    }

    /**
     * Config keys must be declared fields of the node type (catalog whitelist).
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, array{index: int, type: string}>  $nodesByKey
     */
    private function validateConfigWhitelist(Validator $validator, array $nodes, array $nodesByKey): void
    {
        foreach ($nodes as $index => $node) {
            $definition = NodeCatalog::definitionFor((string) $node['type']);

            if ($definition === null) {
                continue;
            }

            $declared = array_column($definition->fields, 'key');
            $configKeys = array_keys((array) ($node['config'] ?? []));
            $unknown = array_diff($configKeys, $declared);

            foreach ($unknown as $field) {
                $validator->errors()->add(
                    "nodes.{$index}.config",
                    __('The configuration field ":field" is not declared for the node type ":type".', ['field' => $field, 'type' => $node['type']]),
                );
            }
        }
    }

    /**
     * The graph must not contain a cycle.
     *
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function validateNoCycle(Validator $validator, array $edges): void
    {
        $graphEdges = array_map(fn (array $edge): array => [
            'source' => (string) $edge['sourceNodeKey'],
            'target' => (string) $edge['targetNodeKey'],
        ], $edges);

        if (GraphValidator::hasCycle($graphEdges)) {
            $validator->errors()->add('edges', __('The graph contains a cycle.'));
        }
    }
}
