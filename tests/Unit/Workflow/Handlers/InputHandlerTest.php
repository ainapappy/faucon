<?php

use App\Data\Workflow\NodeContext;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Data\InputHandler;

/**
 * Build a node context for the data.input handler.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $config
 */
function inputNodeContext(array $input = [], array $config = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n2',
        nodeType: 'data.input',
        nodeName: 'Entrée',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

test('an absent or empty variable name falls back to the payload default', function () {
    $handler = new InputHandler;

    expect($handler->validate([]))->toBe([])
        ->and($handler->validate(['name' => '']))->toBe([]);
});

test('a well formed variable name is accepted', function () {
    expect((new InputHandler)->validate(['name' => 'payload']))->toBe([])
        ->and((new InputHandler)->validate(['name' => 'payload_v2']))->toBe([]);
});

test('an ill formed variable name is rejected', function () {
    $errors = (new InputHandler)->validate(['name' => '9bad']);

    expect($errors)->toBe(['Le nom de la variable « 9bad » est invalide.']);
});

test('the reserved trigger alias is rejected as a variable name', function () {
    $errors = (new InputHandler)->validate(['name' => 'trigger']);

    expect($errors)->toBe(['Le nom de la variable « trigger » est invalide.']);
});

test('the input node exposes its input under its variable name', function () {
    $input = ['email' => 'client@example.com'];
    $context = inputNodeContext(input: $input, config: ['name' => 'contact']);
    $result = (new InputHandler)->execute($context);

    expect($result->output)->toBe($input)
        ->and($result->exposeAs)->toBe('contact')
        ->and($result->branch)->toBeNull()
        ->and($result->isTerminal)->toBeFalse();
});

test('the input node exposes its input under the payload alias by default', function () {
    $input = ['email' => 'client@example.com'];
    $result = (new InputHandler)->execute(inputNodeContext(input: $input));

    expect($result->output)->toBe($input)
        ->and($result->exposeAs)->toBe('payload');
});
