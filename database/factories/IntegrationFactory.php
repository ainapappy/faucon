<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'type' => 'generic_http',
            'name' => fake()->unique()->company(),
            'credentials' => [
                'baseUrl' => 'https://api.'.fake()->domainName(),
                'auth' => 'none',
            ],
            'last_tested_at' => null,
            'last_test_succeeded' => null,
        ];
    }

    /**
     * Indicate the integration is a generic HTTP endpoint.
     */
    public function genericHttp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'generic_http',
            'credentials' => [
                'baseUrl' => 'https://api.exemple.com',
                'auth' => 'none',
            ],
        ]);
    }

    /**
     * Indicate the integration is an SMTP server.
     */
    public function smtp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'smtp',
            'credentials' => [
                'host' => 'smtp.exemple.com',
                'port' => 587,
                'encryption' => 'tls',
            ],
        ]);
    }

    /**
     * Indicate the credentials of the integration.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function withCredentials(array $credentials): static
    {
        return $this->state(fn (array $attributes) => [
            'credentials' => $credentials,
        ]);
    }

    /**
     * Indicate the last connection test succeeded.
     */
    public function testSucceeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_tested_at' => now(),
            'last_test_succeeded' => true,
        ]);
    }

    /**
     * Indicate the last connection test failed.
     */
    public function testFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_tested_at' => now(),
            'last_test_succeeded' => false,
        ]);
    }
}
