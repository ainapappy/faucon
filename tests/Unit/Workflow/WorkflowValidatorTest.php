<?php

use App\Data\Workflow\ExecutionError;
use App\Services\Workflow\Handlers\Data\InputHandler;
use App\Services\Workflow\Handlers\Data\OutputHandler;
use App\Services\Workflow\Handlers\Data\TransformHandler;
use App\Services\Workflow\Handlers\Logic\ConditionHandler;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;
use App\Services\Workflow\NodeHandlerRegistry;
use App\Services\Workflow\WorkflowValidator;

/**
 * Assemble the validator on a fresh registry of the five real handlers.
 */
function testValidator(): WorkflowValidator
{
    $registry = new NodeHandlerRegistry;
    $registry->register(new ManualHandler);
    $registry->register(new InputHandler);
    $registry->register(new TransformHandler);
    $registry->register(new ConditionHandler);
    $registry->register(new OutputHandler);

    return new WorkflowValidator($registry);
}

/**
 * @param  array<string, mixed>  $config
 * @return array{key: string, type: string, name: string, config: array<string, mixed>}
 */
function execNode(string $key, string $type, array $config = []): array
{
    return ['key' => $key, 'type' => $type, 'name' => 'Node '.$key, 'config' => $config];
}

/**
 * @return array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}
 */
function execEdge(string $source, string $target, ?string $handle = null): array
{
    return ['sourceNodeKey' => $source, 'targetNodeKey' => $target, 'sourceHandle' => $handle];
}

test('a valid linear graph produces no errors', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'data.transform'),
        execNode('n3', 'data.output'),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n3')];

    expect(testValidator()->validate($nodes, $edges))->toBe([]);
});

test('a graph without any trigger reports no_trigger', function () {
    $nodes = [execNode('n1', 'data.transform'), execNode('n2', 'data.output')];
    $edges = [execEdge('n1', 'n2')];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0])->toEqual(new ExecutionError(
            nodeKey: null,
            type: 'validation',
            reason: 'no_trigger',
            message: 'Le workflow doit contenir exactement un node déclencheur.',
        ));
});

test('a graph with two triggers reports multiple_triggers with the count', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'trigger.manual'),
        execNode('n3', 'data.output'),
    ];
    $edges = [];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0]->reason)->toBe('multiple_triggers')
        ->and($errors[0]->message)->toBe('Le workflow ne doit contenir qu\'un seul node déclencheur (2 trouvés).');
});

test('a cyclic graph reports cycle', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'data.transform'),
        execNode('n3', 'data.output'),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n3'), execEdge('n3', 'n1')];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0]->nodeKey)->toBeNull()
        ->and($errors[0]->reason)->toBe('cycle')
        ->and($errors[0]->message)->toBe('Le graphe contient un cycle.');
});

test('a type outside the catalog reports unknown_type', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'data.mystery'),
        execNode('n3', 'data.output'),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n3')];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0]->nodeKey)->toBe('n2')
        ->and($errors[0]->reason)->toBe('unknown_type')
        ->and($errors[0]->message)->toBe('Le node « Node n2 » utilise un type inconnu (data.mystery).');
});

test('a catalog type without an executable handler reports handler_missing', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'action.delay'),
        execNode('n3', 'data.output'),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n3')];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0]->nodeKey)->toBe('n2')
        ->and($errors[0]->type)->toBe('action.delay')
        ->and($errors[0]->reason)->toBe('handler_missing')
        ->and($errors[0]->message)->toBe('Le node « Node n2 » utilise un type pas encore exécutable (action.delay).');
});

test('an unsupported condition operator reports invalid_config', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'logic.condition', ['expression' => '', 'operator' => '~']),
        execNode('n3', 'data.output'),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n3')];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0]->nodeKey)->toBe('n2')
        ->and($errors[0]->reason)->toBe('invalid_config')
        ->and($errors[0]->message)->toBe('Erreur de configuration : L’opérateur « ~ » n’est pas supporté.');
});

test('a reserved data.input variable name reports invalid_config', function () {
    $nodes = [
        execNode('n1', 'trigger.manual'),
        execNode('n2', 'data.input', ['name' => 'trigger']),
        execNode('n3', 'data.output'),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n3')];

    $errors = testValidator()->validate($nodes, $edges);

    expect(count($errors))->toBe(1)
        ->and($errors[0]->nodeKey)->toBe('n2')
        ->and($errors[0]->reason)->toBe('invalid_config');
});

test('several problems are reported together', function () {
    $nodes = [
        execNode('n1', 'action.delay'),
        execNode('n2', 'data.input', ['name' => '9bad']),
    ];
    $edges = [execEdge('n1', 'n2'), execEdge('n2', 'n1')];

    $errors = testValidator()->validate($nodes, $edges);

    $reasons = array_map(fn (ExecutionError $error): string => $error->reason, $errors);

    expect($reasons)->toBe(['no_trigger', 'handler_missing', 'invalid_config', 'cycle']);
});
