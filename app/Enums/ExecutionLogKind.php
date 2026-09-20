<?php

namespace App\Enums;

/**
 * Family of a `workflow_execution_logs` row (phase 8): a node execution
 * (one row per node per attempt) or a cycle-of-life event (start, retry,
 * end — `node_key` null).
 */
enum ExecutionLogKind: string
{
    case Node = 'node';
    case Event = 'event';
}
