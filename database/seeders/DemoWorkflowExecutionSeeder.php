<?php

namespace Database\Seeders;

use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Models\Team;
use App\Models\Workflow;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic execution history for the demo workflows (phase 7
 * directive): mostly completed runs, one failed with a readable node error,
 * one cancelled, one running and one pending — spread over the last few
 * days so the executions page is immediately explorable after
 * `migrate:fresh --seed`.
 *
 * Idempotent: workflows that already have executions are left untouched.
 * The seeded `result` payloads mirror the workflow's real graph so the
 * sheet timeline matches what the builder shows.
 */
class DemoWorkflowExecutionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the history of every demo workflow.
     */
    public function run(): void
    {
        $team = Team::query()
            ->where('slug', DemoUserSeeder::TeamSlug)
            ->firstOrFail();

        $team->workflows->each(function (Workflow $workflow): void {
            if ($workflow->executions()->exists()) {
                return;
            }

            $this->seedHistory($workflow);
        });
    }

    /**
     * Seed a small, varied history for one workflow.
     */
    private function seedHistory(Workflow $workflow): void
    {
        $this->seedRun($workflow, ExecutionStatus::Completed, daysAgo: 3, trigger: ExecutionTrigger::Manual);
        $this->seedRun($workflow, ExecutionStatus::Completed, daysAgo: 2, trigger: ExecutionTrigger::Schedule);
        $this->seedRun($workflow, ExecutionStatus::Completed, daysAgo: 1, trigger: ExecutionTrigger::Manual);
        $this->seedRun($workflow, ExecutionStatus::Failed, daysAgo: 1, trigger: ExecutionTrigger::Webhook, failingNode: $workflow->nodes->skip(1)->first()?->key);
        $this->seedRun($workflow, ExecutionStatus::Cancelled, hoursAgo: 5, trigger: ExecutionTrigger::Manual);
        $this->seedRun($workflow, ExecutionStatus::Running, hoursAgo: 1, trigger: ExecutionTrigger::Webhook);
        $this->seedRun($workflow, ExecutionStatus::Pending, hoursAgo: 0, trigger: ExecutionTrigger::Schedule);
    }

    /**
     * Create one execution row with a coherent payload for its status.
     */
    private function seedRun(
        Workflow $workflow,
        ExecutionStatus $status,
        int $daysAgo = 0,
        int $hoursAgo = 0,
        ExecutionTrigger $trigger = ExecutionTrigger::Manual,
        ?string $failingNode = null,
    ): void {
        $startedAt = now()->subDays($daysAgo)->subHours($hoursAgo)->subMinutes(27);

        $attributes = [
            'team_id' => $workflow->team_id,
            'triggered_by' => $trigger,
            'status' => $status,
            'attempt' => $status === ExecutionStatus::Failed ? 2 : 1,
            'input' => $trigger === ExecutionTrigger::Webhook
                ? ['event' => 'demo', 'id' => fake()->numberBetween(1, 999)]
                : [],
            'started_at' => $startedAt,
        ];

        if ($status === ExecutionStatus::Pending) {
            $attributes['started_at'] = null;
        }

        if ($status !== ExecutionStatus::Pending) {
            $result = $this->resultPayload($workflow, $status, $failingNode);

            $attributes['result'] = $result;

            if ($status !== ExecutionStatus::Running) {
                $attributes['finished_at'] = $startedAt->copy()->addMilliseconds($result['durationMs']);
                $attributes['duration_ms'] = $result['durationMs'];
            }
        }

        if ($status === ExecutionStatus::Failed && $failingNode !== null) {
            $attributes['error'] = [
                'nodeKey' => $failingNode,
                'type' => 'action.http',
                'reason' => 'network_error',
                'message' => __('Le node « :name » n’a pas pu joindre le service distant.', [
                    'name' => $workflow->nodes->firstWhere('key', $failingNode)?->name ?? $failingNode,
                ]),
            ];
        }

        $workflow->executions()->create($attributes);
    }

    /**
     * Build an engine-shaped result payload from the real graph.
     *
     * @return array<string, mixed>
     */
    private function resultPayload(Workflow $workflow, ExecutionStatus $status, ?string $failingNode): array
    {
        $nodes = [];
        $total = 0;

        foreach ($workflow->nodes as $node) {
            $failedHere = $failingNode !== null && $node->key === $failingNode;
            $durationMs = $failedHere ? 900 : fake()->numberBetween(5, 600);

            if ($status === ExecutionStatus::Running && $failedHere !== true && $node->is($workflow->nodes->last())) {
                // The last node is still queued in a "running" fixture.
                $nodes[] = [
                    'nodeKey' => $node->key,
                    'type' => $node->type,
                    'name' => $node->name,
                    'status' => 'skipped',
                    'durationMs' => 0,
                    'output' => [],
                    'error' => null,
                ];

                continue;
            }

            $total += $durationMs;

            $nodes[] = [
                'nodeKey' => $node->key,
                'type' => $node->type,
                'name' => $node->name,
                'status' => $failedHere ? 'error' : 'ok',
                'durationMs' => $durationMs,
                'output' => $failedHere ? [] : ['ok' => true],
                'error' => $failedHere ? [
                    'nodeKey' => $node->key,
                    'type' => $node->type,
                    'reason' => 'network_error',
                    'message' => __('Le service distant n’a pas répondu.'),
                ] : null,
            ];
        }

        $errors = [];

        if ($failingNode !== null && $status === ExecutionStatus::Failed) {
            $errors[] = [
                'nodeKey' => $failingNode,
                'type' => 'action.http',
                'reason' => 'network_error',
                'message' => __('Le service distant n’a pas répondu.'),
            ];
        }

        return [
            'status' => $status === ExecutionStatus::Completed ? 'completed' : 'failed',
            'durationMs' => $total,
            'nodes' => $nodes,
            'errors' => $errors,
        ];
    }
}
