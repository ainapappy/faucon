<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowExecution;

class WorkflowExecutionPolicy
{
    /**
     * Determine whether the user can view the executions of the given team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the execution.
     */
    public function view(User $user, WorkflowExecution $execution): bool
    {
        return $user->belongsToTeam($execution->team);
    }

    /**
     * Determine whether the user can cancel the execution — launching and
     * cancelling share the workflow update permission (decision A3).
     */
    public function cancel(User $user, WorkflowExecution $execution): bool
    {
        return $user->hasTeamPermission($execution->team, TeamPermission::WorkflowUpdate);
    }
}
