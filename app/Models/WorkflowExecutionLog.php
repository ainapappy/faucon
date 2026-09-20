<?php

namespace App\Models;

use App\Enums\ExecutionLogKind;
use App\Enums\ExecutionLogLevel;
use Database\Factories\WorkflowExecutionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One journal/timeline row of a persisted execution (phase 8).
 *
 * Two row families (`kind`): a node execution — one row per node per
 * attempt, pre-inserted 'queued' at attempt open then updated — and a
 * cycle-of-life event (started / retry / completed…). Every row carries its
 * French journal message, so the journal is a flat projection at read time.
 *
 * @property int $id
 * @property int $workflow_execution_id
 * @property int $attempt
 * @property ExecutionLogKind $kind
 * @property string|null $node_key
 * @property string|null $node_type
 * @property string|null $node_name
 * @property string|null $status queued|ok|error|skipped (node rows only)
 * @property int|null $duration_ms
 * @property string|null $message
 * @property ExecutionLogLevel $level
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property array<string, mixed>|null $error
 * @property int $offset_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkflowExecution $execution
 */
#[Fillable([
    'workflow_execution_id', 'attempt', 'kind', 'node_key', 'node_type',
    'node_name', 'status', 'duration_ms', 'message', 'level', 'input',
    'output', 'error', 'offset_ms',
])]
class WorkflowExecutionLog extends Model
{
    /** @use HasFactory<WorkflowExecutionLogFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExecutionLogKind::class,
            'level' => ExecutionLogLevel::class,
            'input' => 'array',
            'output' => 'array',
            'error' => 'array',
        ];
    }

    /**
     * Get the execution this row belongs to.
     *
     * @return BelongsTo<WorkflowExecution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    /**
     * Get the prunable query: rows older than the configured retention.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('created_at', '<=', now()->subDays((int) config('workflows.logs.retention_days', 30)));
    }
}
