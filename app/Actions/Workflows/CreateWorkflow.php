<?php

namespace App\Actions\Workflows;

use App\Enums\WorkflowStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;

class CreateWorkflow
{
    /**
     * Create an empty draft workflow for the given team.
     */
    public function handle(User $user, Team $team, string $name, ?string $description): Workflow
    {
        $workflow = new Workflow;

        $workflow->team_id = $team->id;
        $workflow->created_by = $user->id;
        $workflow->name = $name;
        $workflow->description = $description;
        $workflow->status = WorkflowStatus::Draft;
        $workflow->save();

        return $workflow;
    }
}
