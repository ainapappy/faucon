<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;

class IntegrationPolicy
{
    /**
     * Determine whether the user can view the integrations of the given team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create integrations in the given team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::IntegrationCreate);
    }

    /**
     * Determine whether the user can view the integration.
     */
    public function view(User $user, Integration $integration): bool
    {
        return $user->belongsToTeam($integration->team);
    }

    /**
     * Determine whether the user can update the integration.
     */
    public function update(User $user, Integration $integration): bool
    {
        return $user->hasTeamPermission($integration->team, TeamPermission::IntegrationUpdate);
    }

    /**
     * Determine whether the user can delete the integration.
     */
    public function delete(User $user, Integration $integration): bool
    {
        return $user->hasTeamPermission($integration->team, TeamPermission::IntegrationDelete);
    }

    /**
     * Determine whether the user can test the connection (follows update).
     */
    public function test(User $user, Integration $integration): bool
    {
        return $user->hasTeamPermission($integration->team, TeamPermission::IntegrationUpdate);
    }
}
