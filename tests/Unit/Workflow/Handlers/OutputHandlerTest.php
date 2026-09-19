<?php

use App\Data\Workflow\NodeContext;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Data\OutputHandler;

/**
 * Build a node context for the data.output handler.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $config
 */
function outputNodeContext(array $input = [], array $config = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n5',
        nodeType: 'data.output',
        nodeName: 'Sortie',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

test('the output node validates any configuration', function () {
    expect((new OutputHandler)->validate([]))->toBe([]);
});

test('the output node captures its input as the final value and is terminal', function () {
    $input = ['email' => 'client@example.com'];
    $result = (new OutputHandler)->execute(outputNodeContext(input: $input));

    expect($result->output)->toBe(['value' => $input])
        ->and($result->isTerminal)->toBeTrue()
        ->and($result->branch)->toBeNull()
        ->and($result->exposeAs)->toBeNull();
});
