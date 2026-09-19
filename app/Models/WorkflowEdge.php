<?php

namespace App\Models;

use Database\Factories\WorkflowEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $source_node_key
 * @property string $target_node_key
 * @property string|null $source_handle
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 */
#[Fillable(['source_node_key', 'target_node_key', 'source_handle'])]
class WorkflowEdge extends Model
{
    /** @use HasFactory<WorkflowEdgeFactory> */
    use HasFactory;

    /**
     * Get the workflow the edge belongs to.
     *
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
