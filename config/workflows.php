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

        // Maximum time (seconds) to wait while establishing the connection.
        'connect_timeout' => (int) env('WORKFLOW_HTTP_CONNECT_TIMEOUT', 5),

        // Maximum number of manual redirects (each hop re-validated by the
        // SSRF guard — never followed by Guzzle itself).
        'max_redirects' => (int) env('WORKFLOW_HTTP_MAX_REDIRECTS', 2),

        // Maximum response size in bytes (buffered ceiling).
        'max_response_bytes' => (int) env('WORKFLOW_HTTP_MAX_RESPONSE_BYTES', 1048576),
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
