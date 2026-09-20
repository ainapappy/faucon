<?php

namespace App\Http\Controllers\Workflows;

use App\Actions\Workflows\EnsureWebhookEndpoint;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Returns the public webhook url of the workflow (D20).
 *
 * The url carries the token and is therefore never placed in a page
 * prop: the inspector fetches it through this dedicated JSON endpoint.
 */
final class WebhookUrlController extends Controller
{
    public function __invoke(Team $currentTeam, string $workflow, EnsureWebhookEndpoint $ensure): JsonResponse
    {
        $model = $currentTeam->workflows()
            ->whereKey($workflow)
            ->firstOrFail();

        Gate::authorize('view', $model);

        $endpoint = $ensure->handle($model);

        return response()->json(['url' => $endpoint?->url()]);
    }
}
