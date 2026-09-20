<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Enums\TemplateOrigin;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowTemplate;

class WorkflowTemplatePolicy
{
    /**
     * Determine whether the user can view the gallery of the given team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the template: a system one is
     * public to any authenticated user, a team one is team-private.
     */
    public function view(User $user, WorkflowTemplate $template): bool
    {
        if ($template->origin === TemplateOrigin::System) {
            return true;
        }

        return $template->team !== null && $user->belongsToTeam($template->team);
    }

    /**
     * Determine whether the user can instantiate the template in the given
     * (context) team: viewing it plus the workflow creation permission of
     * that team — instantiating creates a workflow, so it may never bypass
     * the create permission (D6/A4). Called with [$template, $team].
     */
    public function use(User $user, WorkflowTemplate $template, Team $team): bool
    {
        return $this->view($user, $template)
            && $user->hasTeamPermission($team, TeamPermission::WorkflowCreate);
    }

    /**
     * Determine whether the user can delete the template: the delete
     * permission of the owning team, never a system template. Defined but
     * NOT routed in phase 9 (first consumer: a future management UI).
     */
    public function delete(User $user, WorkflowTemplate $template): bool
    {
        if ($template->origin === TemplateOrigin::System) {
            return false;
        }

        return $template->team !== null
            && $user->hasTeamPermission($template->team, TeamPermission::WorkflowDelete);
    }
}
