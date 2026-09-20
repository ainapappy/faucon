<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Idempotence journal of the webhook endpoint (one row per accepted
 * X-Request-Id). Purged daily through `model:prune` (D4).
 *
 * @property int $id
 * @property string $token_hash
 * @property string $request_id_hash
 * @property Carbon $received_at
 */
#[Fillable(['token_hash', 'request_id_hash', 'received_at'])]
class WebhookRequest extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /**
     * Get the prunable model query (outside the idempotence window).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'received_at',
            '<',
            now()->minus(days: (int) config('workflows.webhook.idempotence_window_days', 1)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }
}
