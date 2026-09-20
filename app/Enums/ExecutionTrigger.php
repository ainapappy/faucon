<?php

namespace App\Enums;

/**
 * How a workflow execution was launched (phase 7).
 */
enum ExecutionTrigger: string
{
    case Manual = 'manual';
    case Webhook = 'webhook';
    case Schedule = 'schedule';
}
