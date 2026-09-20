<?php

namespace App\Enums;

/**
 * Origin of a workflow template (phase 9): a system template is global
 * (team_id null), a team template belongs to its owning team.
 */
enum TemplateOrigin: string
{
    case System = 'system';
    case Team = 'team';

    /**
     * Get the display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::System => 'Système',
            self::Team => 'Équipe',
        };
    }

    /**
     * Whether this is a system (global) template.
     */
    public function isSystem(): bool
    {
        return $this === self::System;
    }
}
