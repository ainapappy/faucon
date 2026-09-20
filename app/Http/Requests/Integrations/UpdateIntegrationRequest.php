<?php

namespace App\Http\Requests\Integrations;

class UpdateIntegrationRequest extends IntegrationRequest
{
    /**
     * Credentials are optional on update: absent = unchanged.
     *
     * @return array<int, string>
     */
    protected function credentialsRules(): array
    {
        return ['nullable', 'array'];
    }
}
