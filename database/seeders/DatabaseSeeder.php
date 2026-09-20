<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrates the demo seeders and prints the dev/test credentials at the
 * end of `db:seed` / `migrate:fresh --seed`. Everything is idempotent and
 * secret-free: the AI nodes run on `fake/demo`, the SMTP record points at a
 * local Mailpit and the webhook token is a documented dev-only value.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $this->call([
            DemoUserSeeder::class,
            DemoWorkflowSeeder::class,
            DemoWorkflowExecutionSeeder::class,
            DemoIntegrationSeeder::class,
            TemplateSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('Identifiants de démonstration Faucon :');
        $this->command->info('  Connexion     : '.route('login'));
        $this->command->info('  E-mail        : '.DemoUserSeeder::DemoEmail);
        $this->command->info('  Mot de passe  : '.DemoUserSeeder::DemoPassword);
        $this->command->info('  Équipe        : '.DemoUserSeeder::TeamName.' ('.DemoUserSeeder::TeamSlug.')');
        $this->command->info('  Token webhook : '.DemoWorkflowSeeder::WebhookToken);
        $this->command->info('  Webhook       : POST /webhooks/'.DemoWorkflowSeeder::WebhookToken);
    }
}
