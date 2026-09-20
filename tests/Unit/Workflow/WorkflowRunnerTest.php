<?php

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\Handlers\Data\InputHandler;
use App\Services\Workflow\Handlers\Data\OutputHandler;
use App\Services\Workflow\Handlers\Data\TransformHandler;
use App\Services\Workflow\Handlers\Logic\ConditionHandler;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;
use App\Services\Workflow\NodeHandler;
use App\Services\Workflow\NodeHandlerRegistry;
use App\Services\Workflow\WorkflowRunner;
use App\Services\Workflow\WorkflowValidator;

/**
 * Fresh registry with the five real handlers plus the test-local echo handler.
 */
function runnerRegistry(bool $withEcho = true): NodeHandlerRegistry
{
    $registry = new NodeHandlerRegistry;
    $registry->register(new ManualHandler);
    $registry->register(new InputHandler);
    $registry->register(new TransformHandler);
    $registry->register(new ConditionHandler);
    $registry->register(new OutputHandler);

    if ($withEcho) {
        $registry->register(new EchoHandlerForRunnerTest);
    }

    return $registry;
}

/**
 * Runner wired on a real registry and validator (no mocks).
 */
function testRunner(int $timeoutMs = 5000): WorkflowRunner
{
    $registry = runnerRegistry();

    return new WorkflowRunner(new WorkflowValidator($registry), $registry, $timeoutMs);
}

/**
 * @param  array<string, mixed>  $config
 * @return array{key: string, type: string, name: string, config: array<string, mixed>}
 */
function runNode(string $key, string $type, array $config = []): array
{
    return ['key' => $key, 'type' => $type, 'name' => 'Node '.$key, 'config' => $config];
}

/**
 * @return array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}
 */
function runEdge(string $source, string $target, ?string $handle = null): array
{
    return ['sourceNodeKey' => $source, 'targetNodeKey' => $target, 'sourceHandle' => $handle];
}

/**
 * Test-local handler for a type OUTSIDE the catalog (extensibility proof).
 */
final class EchoHandlerForRunnerTest implements NodeHandler
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

test('a linear run completes with outputs in execution order', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.transform', ['expression' => '{{ trigger.email }}']),
        runNode('n3', 'data.output'),
    ];
    $edges = [runEdge('n1', 'n2', 'out'), runEdge('n2', 'n3', 'out')];
    $sample = ['email' => 'client@example.com'];

    $result = testRunner()->run($nodes, $edges, $sample);

    expect($result->status)->toBe('completed')
        ->and($result->errors)->toBe([])
        ->and(array_map(fn ($n) => $n->nodeKey, $result->nodes))->toBe(['n1', 'n2', 'n3'])
        ->and($result->nodes[0]->output)->toBe($sample)
        ->and($result->nodes[1]->output)->toBe(['value' => 'client@example.com'])
        ->and($result->nodes[2]->output)->toBe(['value' => ['value' => 'client@example.com']]);
});

test('the trigger sample is addressable through the trigger alias', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.input', ['name' => 'contact']),
        runNode('n3', 'data.transform', ['expression' => '{{ contact.email }}']),
    ];
    $edges = [runEdge('n1', 'n2', 'out'), runEdge('n2', 'n3', 'out')];
    $sample = ['email' => 'client@example.com'];

    $result = testRunner()->run($nodes, $edges, $sample);

    expect($result->status)->toBe('completed')
        ->and($result->nodes[1]->output)->toBe($sample)
        ->and($result->nodes[2]->output)->toBe(['value' => 'client@example.com']);
});

test('the true branch is followed and the false target is skipped', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'logic.condition', ['expression' => '{{ trigger.v }}', 'value' => 'lead']),
        runNode('n3', 'data.output'),
        runNode('n4', 'data.output'),
    ];
    $edges = [
        runEdge('n1', 'n2', 'out'),
        runEdge('n2', 'n3', 'true'),
        runEdge('n2', 'n4', 'false'),
    ];

    $result = testRunner()->run($nodes, $edges, ['v' => 'lead']);

    expect($result->status)->toBe('completed')
        ->and($result->nodes[2]->status)->toBe('ok')
        ->and($result->nodes[3]->status)->toBe('skipped')
        ->and(array_map(fn ($n) => $n->nodeKey, $result->nodes))->toBe(['n1', 'n2', 'n3', 'n4']);
});

test('the false branch is followed and the true target is skipped', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'logic.condition', ['expression' => '{{ trigger.v }}', 'value' => 'lead']),
        runNode('n3', 'data.output'),
        runNode('n4', 'data.output'),
    ];
    $edges = [
        runEdge('n1', 'n2', 'out'),
        runEdge('n2', 'n3', 'true'),
        runEdge('n2', 'n4', 'false'),
    ];

    $result = testRunner()->run($nodes, $edges, ['v' => 'spam']);

    $byKey = collect($result->nodes)->keyBy(fn ($node) => $node->nodeKey);

    expect($result->status)->toBe('completed')
        ->and($byKey['n3']->status)->toBe('skipped')
        ->and($byKey['n4']->status)->toBe('ok');
});

test('a diamond merge executes the target once with last-edge input winning', function () {
    $nodes = [
        runNode('a', 'trigger.manual'),
        runNode('b', 'data.transform', ['expression' => '{{ trigger.b }}']),
        runNode('c', 'data.transform', ['expression' => '{{ trigger.c }}']),
        runNode('d', 'data.output'),
    ];
    $edges = [
        runEdge('a', 'b', 'out'),
        runEdge('a', 'c', 'out'),
        runEdge('b', 'd'),
        runEdge('c', 'd'),
    ];

    $result = testRunner()->run($nodes, $edges, ['b' => 'Bval', 'c' => 'Cval']);

    expect($result->status)->toBe('completed')
        ->and($result->nodes[1]->output)->toBe(['value' => 'Bval'])
        ->and($result->nodes[2]->output)->toBe(['value' => 'Cval'])
        ->and($result->nodes[3]->output)->toBe(['value' => ['value' => 'Cval']]);
});

test('an isolated node is skipped without any error', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.transform'),
        runNode('n3', 'data.output'),
    ];
    $edges = [runEdge('n1', 'n2', 'out')];

    $result = testRunner()->run($nodes, $edges, ['x' => 1]);

    expect($result->status)->toBe('completed')
        ->and($result->errors)->toBe([])
        ->and($result->nodes[2]->status)->toBe('skipped')
        ->and($result->nodes[2]->durationMs)->toBe(0)
        ->and($result->nodes[2]->output)->toBe([]);
});

test('successors of a terminal output node are skipped', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.output'),
        runNode('n3', 'data.transform'),
    ];
    $edges = [runEdge('n1', 'n2', 'out'), runEdge('n2', 'n3', 'out')];

    $result = testRunner()->run($nodes, $edges, ['x' => 1]);

    expect($result->status)->toBe('completed')
        ->and($result->nodes[1]->output)->toBe(['value' => ['x' => 1]])
        ->and($result->nodes[2]->status)->toBe('skipped');
});

test('a failing node stops the run with an explicit error and skips the rest', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.transform', ['expression' => '{{ trigger.phone }}']),
        runNode('n3', 'data.output'),
    ];
    $edges = [runEdge('n1', 'n2', 'out'), runEdge('n2', 'n3', 'out')];

    $result = testRunner()->run($nodes, $edges, ['email' => 'client@example.com']);

    expect($result->status)->toBe('failed')
        ->and($result->nodes[0]->status)->toBe('ok')
        ->and($result->nodes[1]->status)->toBe('error')
        ->and($result->nodes[1]->error?->nodeKey)->toBe('n2')
        ->and($result->nodes[1]->error?->type)->toBe('data.transform')
        ->and($result->nodes[1]->error?->reason)->toBe('path_not_found')
        ->and($result->nodes[1]->error?->message)->toBe('Le chemin « trigger.phone » est introuvable dans le contexte.')
        ->and($result->nodes[2]->status)->toBe('skipped')
        ->and(count($result->errors))->toBe(1)
        ->and($result->errors[0])->toBe($result->nodes[1]->error);
});

test('an exhausted clock budget fails the run with a timeout error', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.transform'),
    ];
    $edges = [runEdge('n1', 'n2', 'out')];

    $result = testRunner(0)->run($nodes, $edges, []);

    expect($result->status)->toBe('failed')
        ->and(array_map(fn ($n) => $n->status, $result->nodes))->toBe(['skipped', 'skipped'])
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]->nodeKey)->toBeNull()
        ->and($result->errors[0]->reason)->toBe('timeout')
        ->and($result->errors[0]->message)->toBe('L’exécution a dépassé la durée maximale de 0 s.');
});

test('a type registered beyond the catalog runs without touching the runner', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'test.echo'),
        runNode('n3', 'data.transform', ['expression' => '{{ n2.marker }}']),
    ];
    $edges = [runEdge('n1', 'n2', 'out'), runEdge('n2', 'n3', 'out')];
    $registry = runnerRegistry(withEcho: true);
    $runner = new WorkflowRunner(new WorkflowValidator($registry), $registry);

    $result = $runner->run($nodes, $edges, ['marker' => 'extensible']);

    expect($result->status)->toBe('completed')
        ->and($result->nodes[1]->output)->toBe(['marker' => 'extensible'])
        ->and($result->nodes[2]->output)->toBe(['value' => 'extensible']);
});

test('a full run plays both branches across two samples', function () {
    $nodes = [
        runNode('n1', 'trigger.manual'),
        runNode('n2', 'data.transform', ['expression' => '{{ trigger.email }}']),
        runNode('n3', 'logic.condition', ['expression' => '{{ n2.value }}', 'operator' => 'contains', 'value' => '@']),
        runNode('n4', 'data.output'),
        runNode('n5', 'data.transform', ['expression' => 'Reçu : {{ trigger.email }}']),
    ];
    $edges = [
        runEdge('n1', 'n2', 'out'),
        runEdge('n2', 'n3', 'out'),
        runEdge('n3', 'n4', 'true'),
        runEdge('n3', 'n5', 'false'),
    ];

    $lead = testRunner()->run($nodes, $edges, ['email' => 'client@example.com']);
    $leadByKey = collect($lead->nodes)->keyBy(fn ($node) => $node->nodeKey);

    expect($lead->status)->toBe('completed')
        ->and($leadByKey['n3']->output)->toBe(['branch' => 'true', 'value' => true])
        ->and($leadByKey['n4']->output)->toBe(['value' => ['branch' => 'true', 'value' => true]])
        ->and($leadByKey['n5']->status)->toBe('skipped');

    $other = testRunner()->run($nodes, $edges, ['email' => 'not-an-email']);
    $otherByKey = collect($other->nodes)->keyBy(fn ($node) => $node->nodeKey);

    expect($other->status)->toBe('completed')
        ->and($otherByKey['n4']->status)->toBe('skipped')
        ->and($otherByKey['n5']->output)->toBe(['value' => 'Reçu : not-an-email']);
});
