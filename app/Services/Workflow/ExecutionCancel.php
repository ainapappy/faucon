<?php

namespace App\Services\Workflow;

use Illuminate\Support\Facades\Cache;

/**
 * The cancellation flag of an execution, held in the cache.
 *
 * Endpoints only set the flag; the job turns it into the persisted
 * `cancelled` status (at the start of an attempt or between two nodes) and
 * clears it. A flag that is never read simply expires — no side effect.
 */
final class ExecutionCancel
{
    /**
     * The cache key of the cancellation flag for one execution.
     */
    public static function key(int $executionId): string
    {
        return "exec:cancel:{$executionId}";
    }

    /**
     * Request the cancellation of a pending or running execution.
     */
    public static function request(int $executionId): void
    {
        Cache::put(
            self::key($executionId),
            true,
            now()->addSeconds(max(1, (int) config('workflows.execution.cancel_ttl', 3600))),
        );
    }

    /**
     * Whether a cancellation is requested for the execution.
     */
    public static function requested(int $executionId): bool
    {
        return Cache::has(self::key($executionId));
    }

    /**
     * Drop the flag once it has been honored.
     */
    public static function clear(int $executionId): void
    {
        Cache::forget(self::key($executionId));
    }
}
