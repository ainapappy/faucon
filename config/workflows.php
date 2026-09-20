<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP requests (action.http node + connection tests)
    |--------------------------------------------------------------------------
    */

    'http' => [
        // Maximum time (seconds) to wait for a complete response.
        'timeout' => (int) env('WORKFLOW_HTTP_TIMEOUT', 10),

        // Maximum time (seconds) to while establishing the connection.
        'connect_timeout' => (int) env('WORKFLOW_HTTP_CONNECT_TIMEOUT', 5),

        // Maximum number of manual redirects (each hop re-validated by the
        // SSRF guard — never followed by Guzzle itself).
        'max_redirects' => (int) env('WORKFLOW_HTTP_MAX_REDIRECTS', 2),

        // Maximum response size in bytes (buffered ceiling).
        'max_response_bytes' => (int) env('WORKFLOW_HTTP_MAX_RESPONSE_BYTES', 1048576),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queued executions (phase 7 — RunWorkflowJob)
    |--------------------------------------------------------------------------
    */

    'execution' => [
        // Runner budget for ONE attempt of a queued run. The synchronous
        // test-run keeps its own shorter budget (WorkflowRunner default).
        'timeout_ms' => (int) env('WORKFLOW_EXECUTION_TIMEOUT_MS', 120000),

        // Total attempts (initial + retries). Deliberately conservative:
        // a retry re-runs the whole graph, so non-idempotent side effects
        // (emails) repeat.
        'max_tries' => (int) env('WORKFLOW_EXECUTION_MAX_TRIES', 2),

        // Seconds to wait between attempts (config only — not env-bound).
        // Index follows the attempt that just failed; last value repeats.
        'backoff' => [30],

        // Lifetime (seconds) of the cancellation flag in the cache. Must
        // comfortably exceed the longest possible attempt.
        'cancel_ttl' => (int) env('WORKFLOW_EXECUTION_CANCEL_TTL', 3600),

        'retryable_reasons' => [
            'network_error',
            'provider_timeout',
            'provider_unreachable',
            'provider_rate_limited',
        ],

        // Retention (days) of FINAL executions for model:prune (phase 8).
        'retention_days' => (int) env('WORKFLOW_EXECUTIONS_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution logs (phase 8 — journal, redaction, retention)
    |--------------------------------------------------------------------------
    */

    'logs' => [
        // Retention (days) of log rows for model:prune.
        'retention_days' => (int) env('WORKFLOW_LOGS_RETENTION_DAYS', 30),

        // Strings longer than this are truncated with an ellipsis.
        'max_string_chars' => (int) env('WORKFLOW_LOGS_MAX_STRING_CHARS', 2000),

        // A JSON column larger than this becomes an omission marker.
        'max_json_bytes' => (int) env('WORKFLOW_LOGS_MAX_JSON_BYTES', 65536),

        'redacted_keys' => [
            'token', 'api_key', 'apikey', 'api_token', 'access_token', 'refresh_token',
            'id_token', 'secret', 'api_secret', 'client_secret', 'secret_key',
            'private_key', 'password', 'passwd', 'authorization', 'auth', 'credentials',
        ],
        'redacted_key_suffixes' => ['_token', '_secret', '_password', '_api_key', '_apikey', '_credential'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Incoming webhooks (trigger.webhook — used from Lot D onward)
    |--------------------------------------------------------------------------
    */

    'webhook' => [
        // Maximum accepted payload size in bytes.
        'max_payload_bytes' => (int) env('WORKFLOW_WEBHOOK_MAX_PAYLOAD_BYTES', 65536),

        // Maximum JSON nesting depth of the payload.
        'max_json_depth' => (int) env('WORKFLOW_WEBHOOK_MAX_JSON_DEPTH', 10),

        // Requests per minute and per token on the public endpoint.
        'rate_limit_per_minute' => (int) env('WORKFLOW_WEBHOOK_RATE_LIMIT_PER_MINUTE', 60),

        // Idempotence window (days) for the X-Request-Id deduplication.
        'idempotence_window_days' => (int) env('WORKFLOW_WEBHOOK_IDEMPOTENCE_WINDOW_DAYS', 1),
    ],

];
