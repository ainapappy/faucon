<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Integration\ConnectionTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TestIntegrationConnectionController extends Controller
{
    /**
     * Test the integration connection (Policy `test`, JSON response).
     */
    public function __invoke(Team $currentTeam, string $integration, ConnectionTester $tester): JsonResponse
    {
        $model = $currentTeam->integrations()
            ->whereKey($integration)
            ->firstOrFail();

        Gate::authorize('test', $model);

        return response()->json($tester->test($model));
    }
}
