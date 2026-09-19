<?php

use App\Services\Workflow\Exception\NodeExecutionException;
use App\Services\Workflow\ExecutionContext;

test('it stores node outputs keyed by node key', function () {
    $context = new ExecutionContext;

    $context->setNodeOutput('n1', ['email' => 'client@example.com']);

    expect($context->variables())->toBe(['n1' => ['email' => 'client@example.com']]);
});

test('it stores named variables like the trigger alias', function () {
    $context = new ExecutionContext;

    $context->setVariable('trigger', ['email' => 'client@example.com']);
    $context->setVariable('payload', ['email' => 'other@example.com']);

    expect($context->variables())->toBe([
        'trigger' => ['email' => 'client@example.com'],
        'payload' => ['email' => 'other@example.com'],
    ]);
});

test('it resolves paths through the stored variables', function () {
    $context = new ExecutionContext;
    $context->setNodeOutput('n1', ['contact' => ['email' => 'client@example.com']]);

    expect($context->resolve('n1.contact.email'))->toBe('client@example.com')
        ->and($context->resolve('n1.contact.phone'))->toBeNull();
});

test('it interpolates templates through the stored variables', function () {
    $context = new ExecutionContext;
    $context->setVariable('trigger', ['email' => 'client@example.com']);

    expect($context->interpolate('Contact : {{ trigger.email }}'))->toBe('Contact : client@example.com');
});

test('it interpolates strictly and reports the missing path', function () {
    $context = new ExecutionContext;
    $context->setVariable('trigger', []);

    $interpolate = fn (): string => $context->interpolate('{{ trigger.phone }}');

    expect($interpolate)
        ->toThrow(fn (NodeExecutionException $exception) => $exception->reason === 'path_not_found');
});

test('it returns raw values for single placeholder templates', function () {
    $context = new ExecutionContext;
    $context->setVariable('trigger', ['tags' => ['lead', 'vip']]);

    expect($context->value('{{ trigger.tags }}'))->toBe(['lead', 'vip'])
        ->and($context->value('Value : {{ trigger.tags }}'))->toBe('Value : ["lead","vip"]');
});
