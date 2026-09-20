<?php

namespace App\Services\Workflow;

use App\Data\Workflow\ExecutionResult;

/**
 * Decides whether a failed run deserves a queue-level retry.
 *
 * A run is retryable only when EVERY error reason is in the configured
 * allow-list (`workflows.execution.retryable_reasons`) — transient network
 * or provider failures. Validation, SSRF and content errors are never
 * retried: retrying would produce the same outcome.
 */
final class RetryPolicy
{
    /**
     * Whether every error of the run has a retryable reason.
     *
     * @param  ExecutionResult  $result  A run result whose status is 'failed'.
     */
    public static function isRetryable(ExecutionResult $result): bool
    {
        if ($result->status !== 'failed' || $result->errors === []) {
            return false;
        }

        $retryable = (array) config('workflows.execution.retryable_reasons', []);

        foreach ($result->errors as $error) {
            if (! in_array($error->reason, $retryable, true)) {
                return false;
            }
        }

        return true;
    }
}
