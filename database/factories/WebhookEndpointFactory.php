<?php

namespace Database\Factories;

use App\Models\WebhookEndpoint;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(48);

        return [
            'workflow_id' => Workflow::factory()->active(),
            'token' => $token,
            'token_hash' => WebhookEndpoint::hashToken($token),
        ];
    }

    /**
     * Force the endpoint token (its hash is recomputed accordingly).
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => [
            'token' => $token,
            'token_hash' => WebhookEndpoint::hashToken($token),
        ]);
    }
}
