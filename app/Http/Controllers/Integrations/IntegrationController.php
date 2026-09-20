<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Integrations\CreateIntegration;
use App\Actions\Integrations\UpdateIntegration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreIntegrationRequest;
use App\Http\Requests\Integrations\UpdateIntegrationRequest;
use App\Models\Integration;
use App\Models\Team;
use App\Support\IntegrationSummaries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    /**
     * Display the integrations of the current team.
     */
    public function index(Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [Integration::class, $currentTeam]);

        return Inertia::render('settings/Integrations', [
            'integrations' => IntegrationSummaries::forTeam($currentTeam),
            'permissions' => request()->user()->toTeamPermissions($currentTeam),
        ]);
    }

    /**
     * Store a newly created integration.
     */
    public function store(StoreIntegrationRequest $request, Team $currentTeam, CreateIntegration $createIntegration): RedirectResponse
    {
        Gate::authorize('create', [Integration::class, $currentTeam]);

        $createIntegration->handle(
            $currentTeam,
            $request->validated('type'),
            $request->validated('name'),
            $request->credentials(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Integration created.')]);

        return to_route('integrations.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * Update the integration (absent credentials are left unchanged).
     */
    public function update(UpdateIntegrationRequest $request, Team $currentTeam, string $integration, UpdateIntegration $updateIntegration): HttpResponse
    {
        $model = $this->findIntegration($currentTeam, $integration);

        Gate::authorize('update', $model);

        $updateIntegration->handle(
            $model,
            $request->validated('name'),
            $request->credentials(),
        );

        return response()->noContent();
    }

    /**
     * Remove the integration (hard delete: credentials are erased).
     */
    public function destroy(Team $currentTeam, string $integration): RedirectResponse
    {
        $model = $this->findIntegration($currentTeam, $integration);

        Gate::authorize('delete', $model);

        $model->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Integration deleted.')]);

        return to_route('integrations.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * Find the integration through the current team (never a global id).
     */
    private function findIntegration(Team $currentTeam, string $integration): Integration
    {
        return $currentTeam->integrations()
            ->whereKey($integration)
            ->firstOrFail();
    }
}
