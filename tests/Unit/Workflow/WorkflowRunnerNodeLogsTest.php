<?php

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Data\Workflow\NodeRunResult;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;
use App\Services\Workflow\NodeHandler;
use App\Services\Workflow\NodeHandlerRegistry;
use App\Services\Workflow\WorkflowRunner;
use App\Services\Workflow\WorkflowValidator;

/**
 * Fresh registry with the real handlers plus the test-local failing handler.
 */
function nodeLogsRunner(): WorkflowRunner
{
    $registry = new NodeHandlerRegistry;
    $registry->register(new ManualHandler);
    $registry->register(new EchoHandlerForRunnerNodeLogsTest);
    $registry->register(new FailingHandlerForRunnerNodeLogsTest);

    return new WorkflowRunner(new WorkflowValidator($registry), $registry);
}

/**
 * @param  array<string, mixed>  $config
 * @return array{key: string, type: string, name: string, config: array<string, mixed>}
 */
function logsNode(string $key, string $type, array $config = []): array
{
    return ['key' => $key, 'type' => $type, 'name' => 'Node '.$key, 'config' => $config];
}

/**
 * @return array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}
 */
function logsEdge(string $source, string $target, ?string $handle = null): array
{
    return ['sourceNodeKey' => $source, 'targetNodeKey' => $target, 'sourceHandle' => $handle];
}

/**
 * Test-local passthrough handler (type outside the catalog).
 */
final class EchoHandlerForRunnerNodeLogsTest implements NodeHandler
{
    public function type(): string
    {
        return 'test.echo';
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

/**
 * Test-local handler that always fails with a catalogued reason.
 */
final class FailingHandlerForRunnerNodeLogsTest implements NodeHandler
{
    public function type(): string
    {
        return 'test.failing';
    }

    public function validate(array $config): array
    {
        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        throw new NodeExecutionException('boom', __('Le node a explosé.'));
    }
}

test('the hook receives each executed node in order and never the skipped ones', function () {
    // c is unreachable (no edge leads to it) — executed: t, a, b; skipped: c.
    $nodes = [
        logsNode('t', 'trigger.manual'),
        logsNode('a', 'test.echo'),
        logsNode('b', 'test.echo'),
        logsNode('c', 'test.echo'),
    ];
    $edges = [logsEdge('t', 'a'), logsEdge('a', 'b')];

    $received = [];

    nodeLogsRunner()->run($nodes, $edges, ['v' => 1], onNodeResult: function (NodeRunResult $node) use (&$received): void {
        $received[] = $node;
    });

    expect(array_map(fn (NodeRunResult $node) => $node->nodeKey, $received))->toBe(['t', 'a', 'b'])
        ->and(array_map(fn (NodeRunResult $node) => $node->status, $received))->toBe(['ok', 'ok', 'ok']);
});

test('the hook receives the merged input, the sample for the trigger', function () {
    $nodes = [
        logsNode('t', 'trigger.manual'),
        logsNode('a', 'test.echo'),
    ];
    $edges = [logsEdge('t', 'a')];

    $received = [];

    nodeLogsRunner()->run($nodes, $edges, ['email' => 'client@example.com'], onNodeResult: function (NodeRunResult $node) use (&$received): void {
        $received[$node->nodeKey] = $node;
    });

    expect($received['t']->input)->toBe(['email' => 'client@example.com'])
        ->and($received['a']->input)->toBe(['email' => 'client@example.com'])
        ->and($received['a']->output)->toBe(['email' => 'client@example.com']);
});

test('the hook is also called for a failing node, with its input', function () {
    $nodes = [
        logsNode('t', 'trigger.manual'),
        logsNode('f', 'test.failing'),
        logsNode('x', 'test.echo'),
    ];
    $edges = [logsEdge('t', 'f'), logsEdge('f', 'x')];

    $received = [];

    $result = nodeLogsRunner()->run($nodes, $edges, ['v' => 1], onNodeResult: function (NodeRunResult $node) use (&$received): void {
        $received[] = $node;
    });

    expect($result->status)->toBe('failed')
        ->and(array_map(fn (NodeRunResult $node) => [$node->nodeKey, $node->status], $received))
        ->toBe([['t', 'ok'], ['f', 'error']])
        ->and($received[1]->input)->toBe(['v' => 1])
        ->and($received[1]->error?->reason)->toBe('boom');
});

test('without the hook the behaviour is unchanged and no call happens', function () {
    $nodes = [
        logsNode('t', 'trigger.manual'),
        logsNode('a', 'test.echo'),
    ];
    $edges = [logsEdge('t', 'a')];

    $result = nodeLogsRunner()->run($nodes, $edges, ['v' => 1]);

    expect($result->status)->toBe('completed')
        ->and($result->nodes)->toHaveCount(2);
});

test('toArray carries the input and toSummaryArray drops output and input', function () {
    $node = new NodeRunResult(
        nodeKey: 't',
        type: 'trigger.manual',
        name: 'Node t',
        status: 'ok',
        durationMs: 12,
        output: ['email' => 'client@example.com'],
        input: ['email' => 'client@example.com'],
    );

    expect($node->toArray()['input'])->toBe(['email' => 'client@example.com'])
        ->and($node->toArray()['output'])->toBe(['email' => 'client@example.com'])
        ->and($node->toSummaryArray())->toBe([
            'nodeKey' => 't',
            'type' => 'trigger.manual',
            'name' => 'Node t',
            'status' => 'ok',
            'durationMs' => 12,
            'error' => null,
        ]);
});

test('the run-level summary drops node outputs', function () {
    $nodes = [
        logsNode('t', 'trigger.manual'),
        logsNode('a', 'test.echo'),
    ];
    $edges = [logsEdge('t', 'a')];

    $result = nodeLogsRunner()->run($nodes, $edges, ['v' => 1]);
    $summary = $result->toSummaryArray();

    expect($summary['status'])->toBe('completed')
        ->and($summary['durationMs'])->toBeInt()
        ->and($summary['errors'])->toBe([])
        ->and($summary['nodes'][0])->not->toHaveKey('output')
        ->and($summary['nodes'][0])->not->toHaveKey('input')
        ->and($summary['nodes'][0]['nodeKey'])->toBe('t')
        ->and($summary['nodes'][0]['status'])->toBe('ok');
});
