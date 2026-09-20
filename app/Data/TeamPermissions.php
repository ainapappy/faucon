<?php

namespace App\Data;

readonly class TeamPermissions
{
    public function __construct(
        public bool $canUpdateTeam,
        public bool $canDeleteTeam,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
        public bool $canCreateWorkflow,
        public bool $canUpdateWorkflow,
        public bool $canDeleteWorkflow,
        public bool $canCreateIntegration,
        public bool $canUpdateIntegration,
        public bool $canDeleteIntegration,
    ) {
        //
    }
}
