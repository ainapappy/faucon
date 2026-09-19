<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    /**
     * Determine whether the user can view any models of the given team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models in the given team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::WorkflowCreate);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workflow $workflow): bool
    {
        return $user->belongsToTeam($workflow->team);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workflow $workflow): bool
    {
        return $user->hasTeamPermission($workflow->team, TeamPermission::WorkflowUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->hasTeamPermission($workflow->team, TeamPermission::WorkflowDelete);
    }

    /**
     * Determine whether the user can duplicate the model.
     */
    public function duplicate(User $user, Workflow $workflow): bool
    {
        return $user->hasTeamPermission($workflow->team, TeamPermission::WorkflowCreate);
    }
}
