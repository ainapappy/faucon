<?php

namespace Database\Seeders;

use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
use App\Models\WorkflowNode;
use App\Notifications\ExecutionFailedNotification;
use App\Services\Workflow\Log\ExecutionLogMessages;
use Carbon\CarbonInterface;
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

        $demoUser = User::query()->where('email', DemoUserSeeder::DemoEmail)->first();

        if ($demoUser !== null) {
            $this->seedFailureNotifications($team, $demoUser);
        }
    }

    /**
     * Seed the failure notifications of the demo user (phase 10 directive):
     * one manual failed run per workflow, authored by the demo user (A1
     * coherence), produces two UNREAD and one READ notification.
     */
    private function seedFailureNotifications(Team $team, User $author): void
    {
        if ($author->notifications()->exists()) {
            return;
        }

        $notifications = collect();

        $team->workflows->each(function (Workflow $workflow) use ($author, &$notifications): void {
            $execution = $this->seedRun(
                $workflow,
                ExecutionStatus::Failed,
                hoursAgo: 24,
                trigger: ExecutionTrigger::Manual,
                failingNode: $workflow->nodes->skip(1)->first()?->key,
                author: $author,
            );

            $author->notify(new ExecutionFailedNotification($execution->refresh()));

            $notifications->push($author->notifications()->firstOrFail());
        });

        // Age the notifications realistically: the oldest is the READ one.
        $notifications->values()[0]->forceFill(['created_at' => now()->subHours(30)])->save();
        $notifications->values()[0]->markAsRead();
        $notifications->values()[1]->forceFill(['created_at' => now()->subHours(5)])->save();
        $notifications->values()[2]->forceFill(['created_at' => now()->subHours(2)])->save();
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
        ?User $author = null,
    ): WorkflowExecution {
        $startedAt = now()->subDays($daysAgo)->subHours($hoursAgo)->subMinutes(27);

        $attributes = [
            'team_id' => $workflow->team_id,
            'user_id' => $author?->id,
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
                    'name' => $workflow->nodes->firstWhere('key', $failingNode)->name ?? $failingNode,
                ]),
            ];
        }

        $execution = $workflow->executions()->create($attributes);

        if ($status !== ExecutionStatus::Pending) {
            $this->seedLogs($execution, $status, $failingNode, $startedAt);
        }

        return $execution;
    }

    /**
     * Write the journal rows of one seeded run, coherent with its status
     * (phase 8 directive): one row per node per attempt plus the cycle
     * events; the failed run spans TWO attempts (retry in between).
     */
    private function seedLogs(WorkflowExecution $execution, ExecutionStatus $status, ?string $failingNode, CarbonInterface $start): void
    {
        if ($status === ExecutionStatus::Failed) {
            $this->seedAttempt($execution, 1, $failingNode, $start, retry: true);
            $this->seedAttempt($execution, 2, $failingNode, $start);

            return;
        }

        $this->seedAttempt($execution, 1, $failingNode, $start,
            running: $status === ExecutionStatus::Running,
            cancelled: $status === ExecutionStatus::Cancelled);
    }

    /**
     * Write the rows of ONE attempt: node rows (ok / error / skipped /
     * queued) plus the attempt events, with plausible timings.
     */
    private function seedAttempt(
        WorkflowExecution $execution,
        int $attempt,
        ?string $failingNode,
        CarbonInterface $start,
        bool $retry = false,
        bool $running = false,
        bool $cancelled = false,
    ): void {
        $nodes = $execution->workflow->nodes;
        $offset = 0;

        $this->logRow($execution, [
            'attempt' => $attempt,
            'kind' => ExecutionLogKind::Event,
            'level' => ExecutionLogLevel::Info,
            'message' => ExecutionLogMessages::started($this->triggerLabel($execution->workflow)),
            'offset_ms' => 0,
        ]);

        foreach ($nodes as $index => $node) {
            $isFailing = $node->key === $failingNode;
            $isLast = $index === $nodes->count() - 1;
            $queued = $running && $isLast;
            $skipped = $cancelled && $isLast;

            $row = [
                'attempt' => $attempt,
                'kind' => ExecutionLogKind::Node,
                'node_key' => $node->key,
                'node_type' => $node->type,
                'node_name' => $node->name,
            ];

            if ($isFailing) {
                $duration = 900;
                $offset += $duration;

                $row += [
                    'status' => 'error',
                    'duration_ms' => $duration,
                    'level' => ExecutionLogLevel::Error,
                    'message' => ExecutionLogMessages::nodeFailed(__('Le service distant n a pas repondu.')),
                    'input' => $this->nodeInput($node, $execution),
                    'error' => [
                        'nodeKey' => $node->key,
                        'type' => $node->type,
                        'reason' => 'network_error',
                        'message' => __('Le service distant n a pas repondu.'),
                    ],
                    'offset_ms' => $offset,
                ];
            } elseif ($queued || $skipped) {
                $row += [
                    'status' => $queued ? 'queued' : 'skipped',
                    'level' => ExecutionLogLevel::Info,
                    'offset_ms' => $offset,
                ];
            } else {
                $duration = fake()->numberBetween(5, 600);
                $offset += $duration;

                $row += [
                    'status' => 'ok',
                    'duration_ms' => $duration,
                    'level' => ExecutionLogLevel::Ok,
                    'message' => ExecutionLogMessages::nodeCompleted($node->name, $duration),
                    'input' => $this->nodeInput($node, $execution),
                    'output' => ['ok' => true],
                    'offset_ms' => $offset,
                ];
            }

            $this->logRow($execution, $row);
        }

        $this->seedAttemptEnd($execution, $attempt, $retry, $running, $cancelled, $offset);
    }

    /**
     * Write the trailing event of one attempt: retry announcement, terminal
     * line, or nothing while the run is still in flight.
     */
    private function seedAttemptEnd(WorkflowExecution $execution, int $attempt, bool $retry, bool $running, bool $cancelled, int $offset): void
    {
        if ($retry) {
            $this->logRow($execution, [
                'attempt' => $attempt, 'kind' => ExecutionLogKind::Event,
                'level' => ExecutionLogLevel::Info,
                'message' => ExecutionLogMessages::retryScheduled(2, 2, 30),
                'offset_ms' => $offset,
            ]);

            return;
        }

        if ($running) {
            return;
        }

        $failed = $execution->status === ExecutionStatus::Failed;

        $this->logRow($execution, [
            'attempt' => $attempt,
            'kind' => ExecutionLogKind::Event,
            'level' => $failed ? ExecutionLogLevel::Error : ($cancelled ? ExecutionLogLevel::Info : ExecutionLogLevel::Ok),
            'message' => $failed
                ? ExecutionLogMessages::failed(__('Le service distant n a pas repondu.'))
                : ($cancelled ? ExecutionLogMessages::cancelled() : ExecutionLogMessages::completed()),
            'offset_ms' => $offset,
        ]);
    }

    /**
     * Create one log row (payloads included — the demo shows the redaction
     * with a masked token in the webhook input).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function logRow(WorkflowExecution $execution, array $attributes): WorkflowExecutionLog
    {
        return $execution->logs()->create($attributes);
    }

    /**
     * The plausible input of a node: the webhook payload (with a masked
     * secret) for the trigger, an upstream output otherwise.
     *
     * @return array<string, mixed>
     */
    private function nodeInput(WorkflowNode $node, WorkflowExecution $execution): array
    {
        if ($node->type === 'trigger.webhook') {
            return ['event' => 'demo', 'id' => $execution->id, 'api_token' => '[masqué]'];
        }

        return ['ok' => true];
    }

    /**
     * The journal label of the run trigger (mirror of the job rule).
     */
    private function triggerLabel(Workflow $workflow): string
    {
        $trigger = $workflow->nodes->first(fn (WorkflowNode $node) => str_starts_with($node->type, 'trigger.'));

        return match ($trigger?->type) {
            'trigger.webhook' => __('Webhook'),
            'trigger.schedule' => __('Planifié — cron :cron', ['cron' => (string) ($trigger?->config['cron'] ?? '')]),
            default => __('Manuel'),
        };
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
