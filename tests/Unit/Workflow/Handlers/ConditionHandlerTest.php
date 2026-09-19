<?php

use App\Data\Workflow\NodeContext;
use App\Services\Workflow\ExecutionContext;
use App\Services\Workflow\Handlers\Logic\ConditionHandler;

/**
 * Build a node context for the logic.condition handler.
 *
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $config
 */
function conditionNodeContext(array $input = [], array $config = []): NodeContext
{
    return new NodeContext(
        nodeKey: 'n4',
        nodeType: 'logic.condition',
        nodeName: 'Condition',
        config: $config,
        input: $input,
        execution: new ExecutionContext,
    );
}

test('condition validates a missing or empty operator as the equality default', function () {
    $handler = new ConditionHandler;

    expect($handler->validate([]))->toBe([])
        ->and($handler->validate(['operator' => '']))->toBe([])
        ->and($handler->validate(['operator' => null]))->toBe([]);
});

test('condition validates each supported operator', function () {
    $handler = new ConditionHandler;

    foreach (['==', '!=', 'contains', 'empty'] as $operator) {
        expect($handler->validate(['operator' => $operator]))->toBe([]);
    }
});

test('condition rejects an unsupported operator', function () {
    $errors = (new ConditionHandler)->validate(['operator' => '~']);

    expect($errors)->toBe(['L’opérateur « ~ » n’est pas supporté.']);
});

test('equality compares numerics numerically and others strictly', function (mixed $left, mixed $right, bool $expected) {
    $context = conditionNodeContext(config: ['expression' => '{{ trigger.v }}', 'value' => $right]);
    $context->execution->setVariable('trigger', ['v' => $left]);

    $result = (new ConditionHandler)->execute($context);

    expect($result->output['branch'])->toBe($expected ? 'true' : 'false')
        ->and($result->output['value'])->toBe($expected)
        ->and($result->branch)->toBe($expected ? 'true' : 'false');
})->with([
    'equal strings' => ['lead', 'lead', true],
    'different strings' => ['lead', 'spam', false],
    'numeric strings equal' => ['42', '42', true],
    'numeric string and int' => ['42', 42, true],
    'numeric strings loosely equal' => ['42', '042', true],
    'strings differ in case' => ['lead', 'LEAD', false],
    'boolean true and string true' => [true, 'true', true],
    'boolean false and string false' => [false, 'false', true],
    'null and string null' => [null, 'null', true],
    'null and empty string' => [null, '', false],
]);

test('inequality is the negation of equality', function (mixed $left, mixed $right, bool $expected) {
    $context = conditionNodeContext(
        config: ['expression' => '{{ trigger.v }}', 'operator' => '!=', 'value' => $right],
    );
    $context->execution->setVariable('trigger', ['v' => $left]);

    $result = (new ConditionHandler)->execute($context);

    expect($result->output['branch'])->toBe($expected ? 'true' : 'false');
})->with([
    'different strings' => ['lead', 'spam', true],
    'equal strings' => ['lead', 'lead', false],
    'numeric equality is not an inequality' => ['42', 42, false],
]);

test('contains checks the stringified operand within the stringified value', function (mixed $left, mixed $right, bool $expected) {
    $context = conditionNodeContext(
        config: ['expression' => '{{ trigger.v }}', 'operator' => 'contains', 'value' => $right],
    );
    $context->execution->setVariable('trigger', ['v' => $left]);

    $result = (new ConditionHandler)->execute($context);

    expect($result->output['branch'])->toBe($expected ? 'true' : 'false');
})->with([
    'substring found' => ['client@example.com', 'example', true],
    'substring absent' => ['client@example.com', 'example.org', false],
    'null value contains nothing but empty operand' => [null, '', true],
    'array value searched as JSON' => [['a' => 'lead'], 'lead', true],
]);

test('empty matches the PHP empty semantics on the resolved value', function (mixed $left, bool $expected) {
    $context = conditionNodeContext(
        config: ['expression' => '{{ trigger.v }}', 'operator' => 'empty'],
    );
    $context->execution->setVariable('trigger', ['v' => $left]);

    $result = (new ConditionHandler)->execute($context);

    expect($result->output['branch'])->toBe($expected ? 'true' : 'false')
        ->and($result->output['value'])->toBe($expected);
})->with([
    'null is empty' => [null, true],
    'empty string is empty' => ['', true],
    'zero string is empty' => ['0', true],
    'zero int is empty' => [0, true],
    'false is empty' => [false, true],
    'empty array is empty' => [[], true],
    'zero point zero is empty' => [0.0, true],
    'non zero number is not empty' => [42, false],
    'non empty string is not empty' => ['0.0', false],
    'non empty array is not empty' => [['x' => 1], false],
    'true is not empty' => [true, false],
]);

test('condition output always carries the branch string and boolean value', function () {
    $context = conditionNodeContext(config: ['expression' => '{{ trigger.v }}', 'value' => 'lead']);
    $context->execution->setVariable('trigger', ['v' => 'lead']);

    $result = (new ConditionHandler)->execute($context);

    expect($result->output)->toBe(['branch' => 'true', 'value' => true]);
});
