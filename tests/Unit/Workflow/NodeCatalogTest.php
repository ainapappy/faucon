<?php

use App\Enums\NodeCategory;
use App\Services\Workflow\NodeCatalog;

test('the catalog exposes exactly fifteen node types', function () {
    expect(count(NodeCatalog::all()))->toBe(15);
});

test('node type ids match the category dot type format', function () {
    foreach (array_keys(NodeCatalog::all()) as $type) {
        expect($type)->toMatch('/^[a-z]+\.[a-z_]+$/');
    }
});

test('the catalog distributes 3/4/2/3/3 types across the five categories', function () {
    $counts = array_map(fn (NodeCategory $category): int => count(NodeCatalog::typesForCategory($category)), NodeCategory::cases());

    expect($counts)->toBe([3, 4, 2, 3, 3]);
});

test('the manual trigger has one output and no configuration fields', function () {
    $definition = NodeCatalog::definitionFor('trigger.manual');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Manuel')
        ->and($definition->input)->toBeFalse()
        ->and($definition->outputs)->toHaveCount(1)
        ->and($definition->fields)->toHaveCount(0);
});

test('the condition node exposes the true and false outputs', function () {
    $definition = NodeCatalog::definitionFor('logic.condition');

    expect($definition)->not->toBeNull()
        ->and(array_column($definition->outputs, 'id'))->toBe(['true', 'false']);
});

test('the output node terminates the graph with one input, no outputs and no fields', function () {
    $definition = NodeCatalog::definitionFor('data.output');

    expect($definition)->not->toBeNull()
        ->and($definition->label)->toBe('Sortie')
        ->and($definition->description)->toBe('Termine le run et expose le résultat final')
        ->and($definition->icon)->toBe('arrow-right-to-line')
        ->and($definition->input)->toBeTrue()
        ->and($definition->outputs)->toBe([])
        ->and($definition->fields)->toBe([]);
});

test('the output type belongs to the data category and is not a trigger', function () {
    expect(NodeCatalog::categoryFor('data.output'))->toBe(NodeCategory::Data)
        ->and(NodeCatalog::typesForCategory(NodeCategory::Trigger))->not->toContain('data.output');
});

test('the condition node exposes the expression, operator and value fields', function () {
    $definition = NodeCatalog::definitionFor('logic.condition');

    expect($definition)->not->toBeNull()
        ->and(array_column($definition->fields, 'key'))->toBe(['expression', 'operator', 'value'])
        ->and($definition->fields[0]['placeholder'])->toBe('{{ trigger.label }}')
        ->and($definition->fields[1]['type'])->toBe('select')
        ->and($definition->fields[1]['options'])->toBe(['==', '!=', 'contains', 'empty'])
        ->and($definition->fields[2]['type'])->toBe('text');
});

test('unknown types have no definition', function () {
    expect(NodeCatalog::definitionFor('trigger.unknown'))->toBeNull()
        ->and(NodeCatalog::has('trigger.unknown'))->toBeFalse();
});

test('every definition carries the mockup metadata', function () {
    foreach (NodeCatalog::all() as $definition) {
        expect($definition->label)->not->toBeEmpty()
            ->and($definition->description)->not->toBeEmpty()
            ->and($definition->icon)->not->toBeEmpty();
    }
});

test('the category for a type resolves through the catalog', function () {
    expect(NodeCatalog::categoryFor('ai.summary'))->toBe(NodeCategory::Ai)
        ->and(NodeCatalog::categoryFor('trigger.unknown'))->toBeNull();
});

test('the trigger types are resolvable for the trigger node relation', function () {
    expect(NodeCatalog::typesForCategory(NodeCategory::Trigger))->toBe([
        'trigger.webhook',
        'trigger.schedule',
        'trigger.manual',
    ]);
});

test('the webhook definition keeps its select and text fields', function () {
    $definition = NodeCatalog::definitionFor('trigger.webhook');

    expect($definition)->not->toBeNull()
        ->and(array_column($definition->fields, 'key'))->toBe(['method', 'path'])
        ->and($definition->fields[0]['options'])->toBe(['POST', 'GET'])
        ->and($definition->fields[1]['placeholder'])->toBe('hooks/leads');
});
