<?php

namespace App\Services\Integration;

use App\Models\Integration;
use Closure;

/**
 * Boundary used by the workflow handlers to resolve an integration
 * reference (`integration_id` config) at execution time.
 *
 * The lookup is by primary key: the scoping is guaranteed at save time
 * (SaveWorkflowGraphRequest), never re-checked here (D15).
 *
 * @param  Closure(string): Integration|null  $finder  Tests only — bypasses the database.
 */
final class IntegrationResolver
{
    public function __construct(private readonly ?Closure $finder = null) {}

    /**
     * Find an integration by its primary key.
     */
    public function find(?string $id): ?Integration
    {
        if ($id === null || $id === '') {
            return null;
        }

        if ($this->finder !== null) {
            return ($this->finder)($id);
        }

        return Integration::query()->whereKey($id)->first();
    }
}
