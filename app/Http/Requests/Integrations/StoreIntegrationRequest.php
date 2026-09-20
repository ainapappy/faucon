<?php

namespace App\Http\Requests\Integrations;

class StoreIntegrationRequest extends IntegrationRequest
{
    /**
     * Credentials are mandatory on creation.
     *
     * @return array<int, string>
     */
    protected function credentialsRules(): array
    {
        return ['required', 'array'];
    }
}
