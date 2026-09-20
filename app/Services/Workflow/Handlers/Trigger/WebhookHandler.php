<?php

namespace App\Services\Workflow\Handlers\Trigger;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;

/**
 * Handler of the `trigger.webhook` node type: passes the webhook payload
 * through as the trigger output (mirror of trigger.manual, U4).
 */
final class WebhookHandler implements NodeHandler
{
    public function type(): string
    {
        return 'trigger.webhook';
    }

    /**
     * Tolerant: the legacy `method`/`path` config keys of dev graphs are
     * accepted and ignored (no data migration, U1).
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config): array
    {
        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::passthrough($context);
    }
}
