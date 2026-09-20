<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared demo account: one user, its personal team, the owner
 * membership and the current-team pointer. Idempotent (firstOrCreate on
 * the email / slug) so `db:seed` can be replayed freely.
 *
 * Deliberately not factory-based: UserFactory::configure() always spawns
 * a personal team with a random name, which breaks idempotence and the
 * imposed team identity.
 */
class DemoUserSeeder extends Seeder
{
    /** The demo login email. */
    public const string DemoEmail = 'demo@faucon.local';

    /** The demo login password (dev/test only — never a real secret). */
    public const string DemoPassword = 'password';

    /** The demo team display name. */
    public const string TeamName = 'Équipe Démo';

    /** The demo team slug (deterministic idempotence key). */
    public const string TeamSlug = 'equipe-demo';

    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::DemoEmail],
            [
                'name' => 'Utilisateur Démo',
                'password' => self::DemoPassword,
                'email_verified_at' => now(),
            ],
        );

        $team = Team::query()->firstOrCreate(
            ['slug' => self::TeamSlug],
            ['name' => self::TeamName, 'is_personal' => true],
        );

        $team->members()->syncWithoutDetaching([$user->id => ['role' => TeamRole::Owner->value]]);

        if (! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }
    }
}
