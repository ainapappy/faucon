<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a team with a single member in the given role.
 *
 * @return array{User, Team}
 */
function teamWithMember(TeamRole $role = TeamRole::Owner): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $team->members()->attach($user, ['role' => $role->value]);

    return [$user, $team];
}

/**
 * Build a workflow graph API payload from concise node/edge tuples.
 *
 * @param  array<int, array{key: string, type: string, name?: string, config?: array<string, mixed>, positionX?: int, positionY?: int}>  $nodes
 * @param  array<int, array{source: string, target: string, handle?: string|null}>  $edges
 * @return array{nodes: array<int, array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int, positionY: int}>, edges: array<int, array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>}
 */
function graphPayload(array $nodes = [], array $edges = []): array
{
    return [
        'nodes' => array_values(array_map(fn (array $node): array => [
            'key' => $node['key'],
            'type' => $node['type'],
            'name' => $node['name'] ?? $node['type'],
            'config' => $node['config'] ?? [],
            'positionX' => $node['positionX'] ?? 0,
            'positionY' => $node['positionY'] ?? 0,
        ], $nodes)),
        'edges' => array_values(array_map(fn (array $edge): array => [
            'sourceNodeKey' => $edge['source'],
            'targetNodeKey' => $edge['target'],
            'sourceHandle' => $edge['handle'] ?? null,
        ], $edges)),
    ];
}
