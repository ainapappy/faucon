<?php

namespace App\Models;

use App\Enums\TemplateOrigin;
use Database\Factories\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reusable workflow graph (phase 9): global system template when
 * `team_id` is null, team template otherwise. The stored `graph` is the
 * builder payload snapshot (camelCase) read as-is — never re-encoded.
 *
 * Invariant: `origin` ⇔ `team_id` (System ⇔ null, Team ⇔ set), guaranteed
 * at the application level (service, factory states, seeder) and proven by
 * tests — no database CHECK constraint (project convention).
 *
 * @property int $id
 * @property int|null $team_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property TemplateOrigin $origin
 * @property array{nodes: list<array{key: string, type: string, name: string, config: array<string, mixed>, positionX: int, positionY: int}>, edges: list<array{sourceNodeKey: string, targetNodeKey: string, sourceHandle: string|null}>} $graph
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read User|null $creator
 */
#[Fillable(['name', 'description', 'category', 'origin', 'graph'])]
class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => TemplateOrigin::class,
            'graph' => 'array',
            'team_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * Get the owning team of a team template (null for a system template).
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the template (traceability, never authorization).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope templates visible in the gallery of a team: the system ones plus
     * the team's own — never another team's. The OR is wrapped in a closure
     * so the scope stays combinable with `whereKey` (and any other where)
     * without unbounding it.
     *
     * @param  Builder<WorkflowTemplate>  $query
     */
    public function scopeVisibleFor(Builder $query, Team $team): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('team_id')
            ->orWhere('team_id', $team->id));
    }
}
