<?php

namespace App\Services\Workflow\Handlers\Data;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the `data.transform` node type: resolves a single template
 * against the context and wraps the result as its output.
 */
final class TransformHandler implements NodeHandler
{
    public function type(): string
    {
        return 'data.transform';
    }

    public function validate(array $config): array
    {
        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        $expression = $context->config['expression'] ?? '';

        if (! is_string($expression) || $expression === '') {
            return NodeResult::passthrough($context);
        }

        $resolved = $context->value($expression);

        return new NodeResult(
            output: is_array($resolved) ? $resolved : ['value' => $resolved],
        );
    }
}
