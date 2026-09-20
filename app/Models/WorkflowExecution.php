<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use App\Enums\ExecutionTrigger;
use Database\Factories\WorkflowExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A persisted, queued run of a workflow (phase 7).
 *
 * The job writes every status transition; HTTP endpoints never write the
 * status directly (they set the cancellation flag, the job turns it into
 * `cancelled`).
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $team_id
 * @property int|null $user_id
 * @property ExecutionTrigger $triggered_by
 * @property ExecutionStatus $status
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $result
 * @property array{nodeKey: string|null, type: string, reason: string, message: string}|null $error
 * @property int $attempt
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read Team $team
 * @property-read User|null $user
 */
#[Fillable([
    'workflow_id', 'team_id', 'user_id', 'triggered_by', 'status', 'input',
    'result', 'error', 'attempt', 'started_at', 'finished_at', 'duration_ms',
])]
class WorkflowExecution extends Model
{
    /** @use HasFactory<WorkflowExecutionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_by' => ExecutionTrigger::class,
            'status' => ExecutionStatus::class,
            'input' => 'array',
            'result' => 'array',
            'error' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Get the workflow that was executed.
     *
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the team the execution belongs to (denormalized from the workflow).
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who launched a manual execution, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the execution reached a terminal state.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [
            ExecutionStatus::Completed,
            ExecutionStatus::Failed,
            ExecutionStatus::Cancelled,
        ], true);
    }
}
