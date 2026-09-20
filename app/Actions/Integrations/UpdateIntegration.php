<?php

namespace App\Actions\Integrations;

use App\Models\Integration;

class UpdateIntegration
{
    /**
     * Update an integration; absent credentials are left unchanged (D22).
     *
     * @param  array<string, mixed>|null  $credentials
     */
    public function handle(Integration $integration, string $name, ?array $credentials): Integration
    {
        $integration->fill(['name' => $name]);

        if ($credentials !== null) {
            $integration->fill(['credentials' => $credentials]);
        }

        $integration->save();

        return $integration;
    }
}
