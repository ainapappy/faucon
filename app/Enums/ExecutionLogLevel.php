<?php

namespace App\Enums;

/**
 * Severity of a `workflow_execution_logs` row (phase 8), shown with a color
 * tint in the journal (ok → success, error → danger, else neutral).
 */
enum ExecutionLogLevel: string
{
    case Info = 'info';
    case Ok = 'ok';
    case Error = 'error';
}
