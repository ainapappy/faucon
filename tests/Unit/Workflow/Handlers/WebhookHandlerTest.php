<?php

use App\Data\Workflow\NodeContext;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Trigger\WebhookHandler;

/**
 * Build a node context for the trigger.webhook handler.
 *
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $input
 */
function webhookNodeContext(array $config = [], array $input = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n1',
        nodeType: 'trigger.webhook',
        nodeName: 'Webhook',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

test('webhook passes the received input through unchanged', function () {
    $payload = ['event' => 'order.created', 'id' => 7, 'meta' => ['nested' => true]];

    $result = (new WebhookHandler)->execute(webhookNodeContext(input: $payload));

    expect($result->output)->toBe($payload);
});

test('webhook validate tolerates an empty config', function () {
    expect((new WebhookHandler)->validate([]))->toBe([]);
});

test('webhook validate tolerates the legacy method and path config keys', function () {
    expect((new WebhookHandler)->validate([
        'method' => 'POST',
        'path' => 'hooks/leads',
    ]))->toBe([]);
});
