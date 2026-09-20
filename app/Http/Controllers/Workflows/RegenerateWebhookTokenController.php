<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\EnsureWebhookEndpoint;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Regenerates the webhook token (and its hash) transactionally (D20).
 * The previous url dies immediately.
 */
final class RegenerateWebhookTokenController extends Controller
{
    public function __invoke(Team $currentTeam, string $workflow, EnsureWebhookEndpoint $ensure): JsonResponse
    {
        $model = $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();

        Gate::authorize('update', $model);

        $endpoint = DB::transaction(function () use ($model, $ensure): ?WebhookEndpoint {
            $endpoint = $ensure->handle($model);

            if ($endpoint === null) {
                return null;
            }

            $token = Str::random(48);

            $endpoint->update([
                'token' => $token,
                'token_hash' => WebhookEndpoint::hashToken($token),
            ]);

            return $endpoint;
        });

        if ($endpoint === null) {
            abort(404);
        }

        return response()->json(['url' => $endpoint->url()]);
    }
}
