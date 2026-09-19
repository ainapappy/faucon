<?php

namespace App\Services\Workflow;

/**
 * Static graph checks shared by validation and, later, the execution engine.
 */
final class GraphValidator
{
    private const int White = 0;

    private const int Gray = 1;

    private const int Black = 2;

    /**
     * Determine whether the directed graph contains a cycle (three-color DFS).
     *
     * @param  array<int, array{source: string, target: string}>  $edges
     */
    public static function hasCycle(array $edges): bool
    {
        $adjacency = [];

        foreach ($edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        $states = [];

        $visit = function (string $node) use (&$visit, $adjacency, &$states): bool {
            $state = $states[$node] ?? self::White;

            if ($state === self::Gray) {
                return true;
            }

            if ($state === self::Black) {
                return false;
            }

            $states[$node] = self::Gray;

            foreach ($adjacency[$node] ?? [] as $target) {
                if ($visit($target)) {
                    return true;
                }
            }

            $states[$node] = self::Black;

            return false;
        };

        foreach (array_keys($adjacency) as $node) {
            if ($visit($node)) {
                return true;
            }
        }

        return false;
    }
}
