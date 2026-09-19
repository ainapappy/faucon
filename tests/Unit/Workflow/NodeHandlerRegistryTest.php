<?php

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;
use App\Services\Workflow\NodeHandlerRegistry;

/**
 * Minimal handler double declaring a fixed type id.
 */
final class FixedTypeHandler implements NodeHandler
{
    public function __construct(private readonly string $type) {}

    public function type(): string
    {
        return $this->type;
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

test('it registers handlers indexed by their declared type', function () {
    $registry = new NodeHandlerRegistry;
    $handler = new FixedTypeHandler('data.transform');

    $registry->register($handler);

    expect($registry->forType('data.transform'))->toBe($handler);
});

test('it reports whether a type has a registered handler', function () {
    $registry = new NodeHandlerRegistry;
    $registry->register(new FixedTypeHandler('trigger.manual'));

    expect($registry->has('trigger.manual'))->toBeTrue()
        ->and($registry->has('ai.summary'))->toBeFalse();
});

test('it returns null when no handler is registered for the type', function () {
    $registry = new NodeHandlerRegistry;

    expect($registry->forType('ai.summary'))->toBeNull();
});

test('it lists the registered types in registration order', function () {
    $registry = new NodeHandlerRegistry;
    $registry->register(new FixedTypeHandler('trigger.manual'));
    $registry->register(new FixedTypeHandler('data.transform'));
    $registry->register(new FixedTypeHandler('logic.condition'));

    expect($registry->types())->toBe(['trigger.manual', 'data.transform', 'logic.condition']);
});

test('a later registration replaces the handler of the same type', function () {
    $registry = new NodeHandlerRegistry;
    $first = new FixedTypeHandler('data.input');
    $second = new FixedTypeHandler('data.input');

    $registry->register($first);
    $registry->register($second);

    expect($registry->forType('data.input'))->toBe($second);
});
