<?php

namespace App\Services\Workflow\Handlers\Trigger;

use App\Data\Workflow\NodeContext;
use App\Data\Workflow\NodeResult;
use App\Services\Workflow\NodeHandler;
use Cron\CronExpression;
use Throwable;

/**
 * Handler of the `trigger.schedule` node type: a cron-planned run (phase 7).
 *
 * validate() guards the cron expression at graph save (delegated from the
 * save request) and at run time — one place of truth. execute() exposes the
 * schedule context as the trigger output, interpolated as {{ trigger.* }}.
 */
final class ScheduleHandler implements NodeHandler
{
    public function type(): string
    {
        return 'trigger.schedule';
    }

    public function validate(array $config): array
    {
        $cron = trim((string) ($config['cron'] ?? ''));

        if ($cron === '') {
            return [__('L’expression cron est requise pour un node planifié.')];
        }

        try {
            CronExpression::factory($cron)->getNextRunDate();
        } catch (Throwable) {
            return [__('L’expression cron est invalide.')];
        }

        return [];
    }

    public function execute(NodeContext $context): NodeResult
    {
        return new NodeResult(
            output: [
                'triggered_at' => now()->toIso8601String(),
                'cron' => (string) ($context->config['cron'] ?? ''),
            ],
        );
    }
}
