<?php

namespace Database\Seeders;

use App\Enums\IntegrationType;
use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds two dev-only integrations for the « Équipe Démo » team: a generic
 * HTTP endpoint pointing at an example domain and an SMTP record shaped for
 * a local Mailpit instance. Values are plausible placeholders, never real
 * credentials (no auth, no password anywhere).
 *
 * Idempotent: firstOrCreate on team+type+name.
 */
class DemoIntegrationSeeder extends Seeder
{
    /** The example HTTP integration name. */
    public const string HttpName = 'API Exemple';

    /** The local Mailpit SMTP integration name. */
    public const string SmtpName = 'SMTP Local (Mailpit)';

    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::query()
            ->where('slug', DemoUserSeeder::TeamSlug)
            ->firstOrFail();

        $team->integrations()->firstOrCreate(
            ['type' => IntegrationType::GenericHttp->value, 'name' => self::HttpName],
            [
                'credentials' => [
                    'baseUrl' => 'https://api.exemple.com',
                    'auth' => 'none',
                ],
            ],
        );

        $team->integrations()->firstOrCreate(
            ['type' => IntegrationType::Smtp->value, 'name' => self::SmtpName],
            [
                'credentials' => [
                    'host' => '127.0.0.1',
                    'port' => 1025,
                    'encryption' => null,
                ],
            ],
        );
    }
}
