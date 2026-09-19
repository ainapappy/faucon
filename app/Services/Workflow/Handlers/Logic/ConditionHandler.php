<?php

namespace App\Services\Workflow\Handlers\Logic;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the `logic.condition` node type.
 *
 * Config contract: {expression, operator, value}. `operator` absent or empty
 * defaults to `==`; `value` absent defaults to null; an empty expression
 * resolves null (retro-compatible with phase 3 graphs).
 */
final class ConditionHandler implements NodeHandler
{
    public function type(): string
    {
        return 'logic.condition';
    }

    public function validate(array $config): array
    {
        $operator = $config['operator'] ?? null;

        if (! is_string($operator) || $operator === '') {
            return [];
        }

        if (! in_array($operator, ['==', '!=', 'contains', 'empty'], true)) {
            return [__('L’opérateur « :op » n’est pas supporté.', ['op' => $operator])];
        }

        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        $expression = $context->config['expression'] ?? null;
        $left = is_string($expression) && $expression !== ''
            ? $context->value($expression)
            : null;
        $operator = $context->config['operator'] ?? null;
        $operator = is_string($operator) && $operator !== '' ? $operator : '==';
        $right = $context->config['value'] ?? null;

        $evaluated = match ($operator) {
            '!=' => ! $this->equals($left, $right),
            'contains' => $this->contains($left, $right),
            'empty' => empty($left),
            default => $this->equals($left, $right),
        };

        $branch = $evaluated ? 'true' : 'false';

        return new NodeResult(
            output: ['branch' => $branch, 'value' => $evaluated],
            branch: $branch,
        );
    }

    /**
     * Equality with normalization: the literal strings 'true'/'false'/'null'
     * become bool/null on BOTH sides; numeric sides compare numerically;
     * anything else compares strictly.
     */
    private function equals(mixed $left, mixed $right): bool
    {
        $left = $this->normalize($left);
        $right = $this->normalize($right);

        if (is_numeric($left) && is_numeric($right)) {
            return $left == $right;
        }

        return $left === $right;
    }

    /**
     * Normalize the special literal strings to their PHP values.
     */
    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return match ($value) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => $value,
            };
        }

        return $value;
    }

    /**
     * Substring check on the stringified sides (null → '', array → JSON).
     */
    private function contains(mixed $left, mixed $right): bool
    {
        return str_contains($this->stringify($left), $this->stringify($right));
    }

    private function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }
}
