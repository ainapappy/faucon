<?php

use App\Data\Workflow\ExecutionError;
use App\Data\Workflow\ExecutionResult;
use App\Services\Workflow\RetryPolicy;

function retryResult(array $reasons, string $status = 'failed'): ExecutionResult
{
    return new ExecutionResult(
        status: $status,
        durationMs: 10,
        nodes: [],
        errors: array_map(
            fn (string $reason): ExecutionError => new ExecutionError(
                nodeKey: 'n',
                type: 'action.http',
                reason: $reason,
                message: 'm',
            ),
            $reasons,
        ),
    );
}

test('a failed run whose errors are all transient is retryable', function () {
    expect(RetryPolicy::isRetryable(retryResult(['network_error'])))->toBeTrue()
        ->and(RetryPolicy::isRetryable(retryResult(['provider_timeout'])))->toBeTrue()
        ->and(RetryPolicy::isRetryable(retryResult(['provider_unreachable'])))->toBeTrue()
        ->and(RetryPolicy::isRetryable(retryResult(['provider_rate_limited'])))->toBeTrue();
});

test('mixed reasons are not retryable', function () {
    expect(RetryPolicy::isRetryable(retryResult(['network_error', 'http_request_failed'])))->toBeFalse()
        ->and(RetryPolicy::isRetryable(retryResult(['blocked_host', 'network_error'])))->toBeFalse();
});

test('validation and content errors are never retryable', function () {
    foreach (['unknown_type', 'blocked_host', 'invalid_config', 'structured_output_invalid', 'http_request_failed', 'timeout', 'no_trigger'] as $reason) {
        expect(RetryPolicy::isRetryable(retryResult([$reason])))->toBeFalse();
    }
});

test('a completed run is never retryable', function () {
    expect(RetryPolicy::isRetryable(retryResult([], status: 'completed')))->toBeFalse()
        ->and(RetryPolicy::isRetryable(retryResult(['network_error'], status: 'completed')))->toBeFalse();
});

test('the allow-list is config-driven', function () {
    config(['workflows.execution.retryable_reasons' => ['http_request_failed']]);

    expect(RetryPolicy::isRetryable(retryResult(['http_request_failed'])))->toBeTrue();
});
