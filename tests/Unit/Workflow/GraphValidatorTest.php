<?php

use App\Services\Workflow\GraphValidator;

test('a linear chain of edges has no cycle', function () {
    expect(GraphValidator::hasCycle([
        ['source' => 'a', 'target' => 'b'],
        ['source' => 'b', 'target' => 'c'],
        ['source' => 'c', 'target' => 'd'],
    ]))->toBeFalse();
});

test('a diamond graph has no cycle', function () {
    expect(GraphValidator::hasCycle([
        ['source' => 'a', 'target' => 'b'],
        ['source' => 'a', 'target' => 'c'],
        ['source' => 'b', 'target' => 'd'],
        ['source' => 'c', 'target' => 'd'],
    ]))->toBeFalse();
});

test('disconnected components are traversed without a cycle', function () {
    expect(GraphValidator::hasCycle([
        ['source' => 'a', 'target' => 'b'],
        ['source' => 'c', 'target' => 'd'],
    ]))->toBeFalse();
});

test('two nodes referencing each other form a cycle', function () {
    expect(GraphValidator::hasCycle([
        ['source' => 'a', 'target' => 'b'],
        ['source' => 'b', 'target' => 'a'],
    ]))->toBeTrue();
});

test('three nodes chained back to the first form a cycle', function () {
    expect(GraphValidator::hasCycle([
        ['source' => 'a', 'target' => 'b'],
        ['source' => 'b', 'target' => 'c'],
        ['source' => 'c', 'target' => 'a'],
    ]))->toBeTrue();
});

test('an edge from a node to itself forms a cycle', function () {
    expect(GraphValidator::hasCycle([
        ['source' => 'a', 'target' => 'a'],
    ]))->toBeTrue();
});

test('an empty edge list has no cycle', function () {
    expect(GraphValidator::hasCycle([]))->toBeFalse();
});
