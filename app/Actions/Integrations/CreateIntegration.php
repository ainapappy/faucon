<?php

namespace App\Actions\Integrations;

use App\Models\Integration;
use App\Models\Team;

class CreateIntegration
{
    /**
     * Create an integration inside the given team (scoping by relation).
     *
     * @param  array<string, mixed>  $credentials
     */
    public function handle(Team $team, string $type, string $name, array $credentials): Integration
    {
        return $team->integrations()->create([
            'type' => $type,
            'name' => $name,
            'credentials' => $credentials,
        ]);
    }
}
