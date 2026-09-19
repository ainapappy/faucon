<?php

namespace App\Services\Workflow\Handlers\Data;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the `data.output` node type: captures the final result of the
 * run and marks the node terminal — downstream traversal stops there (D7).
 */
final class OutputHandler implements NodeHandler
{
    public function type(): string
    {
        return 'data.output';
    }

    public function validate(array $config): array
    {
        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        return new NodeResult(
            output: ['value' => $context->input],
            isTerminal: true,
        );
    }
}
