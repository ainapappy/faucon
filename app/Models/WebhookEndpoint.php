<?php

namespace App\Models;

use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The public endpoint record of a workflow (one per workflow at most).
 *
 * The token is stored encrypted; lookups go through `token_hash`
 * (SHA-256 hex) because the encrypted cast is not queryable (D2).
 *
 * @property int $id
 * @property int $workflow_id
 * @property string $token
 * @property string $token_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 */
#[Fillable(['workflow_id', 'token', 'token_hash'])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    /**
     * Hash a raw token for indexed lookups.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Get the workflow the endpoint belongs to.
     *
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * The public URL carrying the decrypted token.
     */
    public function url(): string
    {
        return route('webhooks.handle', ['token' => $this->token]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
        ];
    }
}
