<?php

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;

/**
 * Build a node context for the manual trigger handler.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $config
 */
function manualNodeContext(array $input = [], array $config = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n1',
        nodeType: 'trigger.manual',
        nodeName: 'Manuel',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

test('the manual trigger accepts any configuration', function () {
    expect((new ManualHandler)->validate([]))->toBe([]);
});

test('the manual trigger passes its input through as output', function () {
    $input = ['email' => 'client@example.com'];
    $handler = new ManualHandler;

    $result = $handler->execute(manualNodeContext(input: $input));

    expect($result)->toEqual(new NodeResult($input))
        ->and($result->output)->toBe($input)
        ->and($result->branch)->toBeNull()
        ->and($result->isTerminal)->toBeFalse()
        ->and($result->exposeAs)->toBeNull();
});
