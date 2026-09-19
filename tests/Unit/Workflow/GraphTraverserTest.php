<?php

use App\Services\Workflow\GraphTraverser;

/**
 * @param  array<string, mixed>  $config
 * @return array{key: string, type: string, name: string, config: array<string, mixed>}
 */
function travNode(string $key, string $type, array $config = []): array
{
    return ['key' => $key, 'type' => $type, 'name' => 'Node '.$key, 'config' => $config];
}

/**
 * @return array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}
 */
function travEdge(string $source, string $target, ?string $handle = null): array
{
    return ['sourceNodeKey' => $source, 'targetNodeKey' => $target, 'sourceHandle' => $handle];
}

test('it returns a node by its key and null for an unknown key', function () {
    $traverser = new GraphTraverser(
        [travNode('n1', 'trigger.manual'), travNode('n2', 'data.output')],
        [],
    );

    expect($traverser->node('n1'))->toBe(travNode('n1', 'trigger.manual'))
        ->and($traverser->node('unknown'))->toBeNull();
});

test('successorsOf follows every outgoing edge when no branch is announced', function () {
    $traverser = new GraphTraverser(
        [travNode('a', 'trigger.manual'), travNode('b', 'data.transform'), travNode('c', 'data.output')],
        [travEdge('a', 'b', 'out'), travEdge('a', 'c')],
    );

    expect($traverser->successorsOf('a', null))->toBe(['b', 'c']);
});

test('successorsOf filters outgoing edges by the announced branch', function () {
    $traverser = new GraphTraverser(
        [travNode('a', 'logic.condition'), travNode('t', 'data.output'), travNode('f', 'data.output')],
        [travEdge('a', 't', 'true'), travEdge('a', 'f', 'false')],
    );

    expect($traverser->successorsOf('a', 'true'))->toBe(['t'])
        ->and($traverser->successorsOf('a', 'false'))->toBe(['f']);
});

test('successorsOf still follows unhandled edges when a branch is announced', function () {
    $traverser = new GraphTraverser(
        [travNode('a', 'logic.condition'), travNode('t', 'data.output'), travNode('u', 'data.output')],
        [travEdge('a', 't', 'true'), travEdge('a', 'u')],
    );

    expect($traverser->successorsOf('a', 'true'))->toBe(['t', 'u']);
});

test('successorsOf returns nothing for a node without outgoing edges', function () {
    $traverser = new GraphTraverser([travNode('a', 'data.output')], []);

    expect($traverser->successorsOf('a', null))->toBe([])
        ->and($traverser->successorsOf('unknown', null))->toBe([]);
});

test('mergeInputs merges executed upstream outputs in edge order, last one wins', function () {
    $traverser = new GraphTraverser(
        [travNode('a', 'trigger.manual'), travNode('b', 'data.transform'), travNode('c', 'data.transform'), travNode('d', 'data.output')],
        [travEdge('a', 'b', 'out'), travEdge('a', 'c', 'out'), travEdge('b', 'd'), travEdge('c', 'd')],
    );
    $executed = [
        'b' => ['value' => 'B'],
        'c' => ['value' => 'C'],
    ];

    expect($traverser->mergeInputs('d', $executed))->toBe(['value' => 'C']);
});

test('mergeInputs contributes once per upstream node even across two edges', function () {
    $traverser = new GraphTraverser(
        [travNode('a', 'data.transform'), travNode('d', 'data.output')],
        [travEdge('a', 'd'), travEdge('a', 'd', 'out')],
    );
    $executed = ['a' => ['value' => 'once']];

    expect($traverser->mergeInputs('d', $executed))->toBe(['value' => 'once']);
});

test('mergeInputs ignores upstream nodes that have not executed', function () {
    $traverser = new GraphTraverser(
        [travNode('a', 'data.transform'), travNode('d', 'data.output')],
        [travEdge('a', 'd')],
    );

    expect($traverser->mergeInputs('d', []))->toBe([]);
});
