<?php

use App\Data\Workflow\NodeContext;
use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Data\TransformHandler;

/**
 * Build a node context for the data.transform handler.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $config
 */
function transformNodeContext(array $input = [], array $config = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n3',
        nodeType: 'data.transform',
        nodeName: 'Transformation',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

test('transform validates every configuration', function () {
    expect((new TransformHandler)->validate([]))->toBe([]);
});

test('transform without expression passes its input through', function () {
    $input = ['email' => 'client@example.com'];
    $result = (new TransformHandler)->execute(transformNodeContext(input: $input));

    expect($result->output)->toBe($input)
        ->and($result->branch)->toBeNull()
        ->and($result->isTerminal)->toBeFalse()
        ->and($result->exposeAs)->toBeNull();
});

test('transform with an empty expression passes its input through', function () {
    $input = ['email' => 'client@example.com'];
    $result = (new TransformHandler)->execute(
        transformNodeContext(input: $input, config: ['expression' => '']),
    );

    expect($result->output)->toBe($input);
});

test('transform with a scalar expression wraps the resolved value', function () {
    $context = transformNodeContext(
        input: [],
        config: ['expression' => '{{ trigger.email }}'],
    );
    $context->execution->setVariable('trigger', ['email' => 'client@example.com']);

    $result = (new TransformHandler)->execute($context);

    expect($result->output)->toBe(['value' => 'client@example.com']);
});

test('transform copies an array result by structure, not wrapped', function () {
    $payload = ['email' => 'client@example.com', 'tags' => ['lead']];
    $context = transformNodeContext(
        input: [],
        config: ['expression' => '{{ trigger }}'],
    );
    $context->execution->setVariable('trigger', $payload);

    $result = (new TransformHandler)->execute($context);

    expect($result->output)->toBe($payload);
});

test('transform resolves embedded placeholders in a text template', function () {
    $context = transformNodeContext(
        input: [],
        config: ['expression' => 'Bonjour {{ trigger.name }}'],
    );
    $context->execution->setVariable('trigger', ['name' => 'Aina']);

    $result = (new TransformHandler)->execute($context);

    expect($result->output)->toBe(['value' => 'Bonjour Aina']);
});

test('transform throws path_not_found for a missing path', function () {
    $context = transformNodeContext(
        input: [],
        config: ['expression' => '{{ trigger.phone }}'],
    );
    $context->execution->setVariable('trigger', []);

    $execute = fn (): mixed => (new TransformHandler)->execute($context);

    expect($execute)->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});
