<?php

use App\Services\Workflow\Handlers\Data\InputHandler;
use App\Services\Workflow\Handlers\Data\OutputHandler;
use App\Services\Workflow\Handlers\Data\TransformHandler;
use App\Services\Workflow\Handlers\Logic\ConditionHandler;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;
use App\Services\Workflow\NodeHandlerRegistry;
use App\Services\Workflow\WorkflowRunner;
use App\Services\Workflow\WorkflowValidator;

/**
 * Fresh registry with the real handlers (same wiring as WorkflowRunnerTest,
 * locally redefined so this file runs standalone).
 */
function cancelRunner(): WorkflowRunner
{
    $registry = new NodeHandlerRegistry;
    $registry->register(new ManualHandler);
    $registry->register(new InputHandler);
    $registry->register(new TransformHandler);
    $registry->register(new ConditionHandler);
    $registry->register(new OutputHandler);

    return new WorkflowRunner(new WorkflowValidator($registry), $registry);
}

/**
 * @param  array<string, mixed>  $config
 * @return array{key: string, type: string, name: string, config: array<string, mixed>}
 */
function cancelNode(string $key, string $type, array $config = []): array
{
    return ['key' => $key, 'type' => $type, 'name' => 'Node '.$key, 'config' => $config];
}

/**
 * @return array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}
 */
function cancelEdge(string $source, string $target, ?string $handle = null): array
{
    return ['sourceNodeKey' => $source, 'targetNodeKey' => $target, 'sourceHandle' => $handle];
}

test('a before-node hook that always answers true stops the run before the trigger', function () {
    $runner = cancelRunner();

    $result = $runner->run(
        [cancelNode('t', 'trigger.manual'), cancelNode('o', 'data.output')],
        [cancelEdge('t', 'o')],
        [],
        beforeNode: fn (string $key): bool => true,
    );

    expect($result->status)->toBe('cancelled')
        ->and($result->errors)->toBe([])
        ->and($result->nodes[0]->status)->toBe('skipped')
        ->and($result->nodes[1]->status)->toBe('skipped');
});

test('the hook receives the node keys in traversal order', function () {
    $runner = cancelRunner();
    $seen = [];

    $result = $runner->run(
        [cancelNode('t', 'trigger.manual'), cancelNode('o', 'data.output')],
        [cancelEdge('t', 'o')],
        [],
        beforeNode: function (string $key) use (&$seen): bool {
            $seen[] = $key;

            return false;
        },
    );

    expect($seen)->toBe(['t', 'o'])
        ->and($result->status)->toBe('completed');
});

test('cancelling after the trigger marks the remaining nodes skipped', function () {
    $runner = cancelRunner();
    $calls = 0;

    $result = $runner->run(
        [cancelNode('t', 'trigger.manual'), cancelNode('o', 'data.output')],
        [cancelEdge('t', 'o')],
        [],
        beforeNode: function (string $key) use (&$calls): bool {
            $calls++;

            return $calls > 1; // true only BEFORE the second node
        },
    );

    expect($result->status)->toBe('cancelled')
        ->and($result->nodes[0]->status)->toBe('ok')
        ->and($result->nodes[1]->status)->toBe('skipped');
});

test('without a hook the run is unaffected (backwards compatible)', function () {
    $runner = cancelRunner();

    $result = $runner->run(
        [cancelNode('t', 'trigger.manual'), cancelNode('o', 'data.output')],
        [cancelEdge('t', 'o')],
        [],
    );

    expect($result->status)->toBe('completed');
});

test('the timeout budget can be overridden per run', function () {
    $runner = cancelRunner(); // constructor budget: 5000 ms

    $result = $runner->run(
        [cancelNode('t', 'trigger.manual'), cancelNode('o', 'data.output')],
        [cancelEdge('t', 'o')],
        [],
        timeoutMs: 0,
    );

    expect($result->status)->toBe('failed')
        ->and($result->errors[0]->reason)->toBe('timeout')
        ->and($result->errors[0]->nodeKey)->toBeNull();
});

test('a cancelled run reports the elapsed duration and no engine error', function () {
    $runner = cancelRunner();

    $result = $runner->run(
        [cancelNode('t', 'trigger.manual')],
        [],
        [],
        beforeNode: fn (string $key): bool => $key === 't',
    );

    expect($result->status)->toBe('cancelled')
        ->and($result->durationMs)->toBeInt()
        ->and($result->errors)->toBe([]);
});
