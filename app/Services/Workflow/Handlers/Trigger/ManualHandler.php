<?php

namespace App\Services\Workflow\Handlers\Trigger;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the `trigger.manual` node type: launches a run from the UI.
 */
final class ManualHandler implements NodeHandler
{
    public function type(): string
    {
        return 'trigger.manual';
    }

    public function validate(array $config): array
    {
        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::passthrough($context);
    }
}
