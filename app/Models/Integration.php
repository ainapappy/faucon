<?php

namespace App\Models;

use App\Enums\IntegrationType;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property IntegrationType $type
 * @property string $name
 * @property array<string, mixed> $credentials
 * @property Carbon|null $last_tested_at
 * @property bool|null $last_test_succeeded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['type', 'name', 'credentials', 'last_tested_at', 'last_test_succeeded'])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use HasFactory;

    /**
     * Get the team the integration belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IntegrationType::class,
            'credentials' => 'encrypted:array',
            'last_tested_at' => 'datetime',
            'last_test_succeeded' => 'boolean',
        ];
    }
}
