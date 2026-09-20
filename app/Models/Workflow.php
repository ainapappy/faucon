<?php

namespace App\Models;

use App\Enums\NodeCategory;
use App\Enums\WorkflowStatus;
use App\Services\Workflow\NodeCatalog;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $description
 * @property WorkflowStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $creator
 * @property-read Collection<int, WorkflowNode> $nodes
 * @property-read Collection<int, WorkflowEdge> $edges
 * @property-read WorkflowNode|null $triggerNode
 * @property-read WebhookEndpoint|null $webhookEndpoint
 */
#[Fillable(['name', 'description', 'status'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
        ];
    }

    /**
     * Get the team the workflow belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the workflow.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all the nodes of the workflow graph.
     *
     * @return HasMany<WorkflowNode, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    /**
     * Get all the edges of the workflow graph.
     *
     * @return HasMany<WorkflowEdge, $this>
     */
    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    /**
     * Get the trigger node of the graph, if any.
     *
     * @return HasOne<WorkflowNode, $this>
     */
    public function triggerNode(): HasOne
    {
        return $this->hasOne(WorkflowNode::class)
            ->whereIn('type', NodeCatalog::typesForCategory(NodeCategory::Trigger));
    }

    /**
     * Get the public webhook endpoint of the workflow, if any.
     *
     * @return HasOne<WebhookEndpoint, $this>
     */
    public function webhookEndpoint(): HasOne
    {
        return $this->hasOne(WebhookEndpoint::class);
    }
}
