# Phase 1 — Rapport d'audit du squelette

> Date : 2026-09-18 · Portée : état réel du projet à l'entrée de la phase 1 (Architecture & Foundations).
> Méthode : lecture du code + commandes (`application-info` Boost, `composer show --direct`, `package.json`, `php artisan about`, `php artisan route:list`, exécution de la chaîne qualité).

## 1. Stack — versions réelles

| Couche | Version constatée |
|---|---|
| PHP | 8.4.4 |
| Laravel | 13.32.0 |
| Base de données | Dev : **MySQL** (`faucon@127.0.0.1:3306`, `.env` basculé pendant la session d'audit) · Tests : sqlite `:memory:` (`phpunit.xml`) · défaut config : sqlite |
| Fortify | 1.39.0 (+ Sanctum 4.3.3, `laravel/passkeys` 0.2.1 installé, non câblé) |
| Inertia (back) | `inertiajs/inertia-laravel` 3.3.4 |
| Inertia (front) | `@inertiajs/vue3` / `@inertiajs/core` / `@inertiajs/vite` 3.7.1 |
| Vue | 3.5.43 · TypeScript 5.9.3 |
| Vite | 8.3.0 via **vite-plus 0.3.0** (`vp build/dev/check`) |
| Tailwind | 4.3.3 (`@tailwindcss/vite`) · shadcn-vue (reka-ui 2.10.4, lucide, vue-sonner 2.0.9, @vueuse/core 12.8.2) |
| Wayfinder | `laravel/wayfinder` 0.1.21 + `@laravel/vite-plugin-wayfinder` 0.1.10 |
| Qualité | Pest 5.2.1 (+ plugins laravel/arch/mutate), Larastan 3.12.1 (PHPStan 2.2.14, niveau 7), Pint 1.32.1, oxlint/oxfmt (via vite-plus) |
| Queue / cache / session / mail | database / database / database / log (dev) |

Remarques :
- **Pas de Vitest configuré** contrairement à ce qu'annonçait le README : les checks front passent par `npm run check` (oxlint type-aware, `denyWarnings`) et `npm run types:check` (vue-tsc). Vitest est présent transitivement via vite-plus mais aucune spec front n'existe.
- `laravel/passkeys` est installé avec ses migrations **non publiées** (publication requise le jour où la fonctionnalité est câblée — cohérent avec l'absence d'usage dans le code).

## 2. Inventaire backend (`app/`, `routes/`, `database/`, `config/`, `tests/`)

### app/ — 50 fichiers

- **Actions** : `Fortify/ResetUserPassword`, `Teams/CreateTeam`.
- **Concerns** : `HasTeams`, `GeneratesUniqueTeamSlugs`, `PasswordValidationRules`, `ProfileValidationRules`.
- **Data** : `TeamPermissions`, `UserTeam` (DTOs simples).
- **Enums** : `TeamRole` (Owner/Admin/Member + helpers), `TeamPermission` (`domain:action`).
- **Http/Controllers** : `DashboardController` (invokable), `Settings/{Profile,Security}`, `Teams/{Team,TeamInvitation,TeamMember}`.
- **Http/Middleware** : `EnsureTeamMembership`, `HandleAppearance`, `HandleInertiaRequests`, `SetTeamUrlDefaults`.
- **Http/Requests** : Settings (PasswordUpdate, ProfileDelete, ProfileUpdate, TwoFactorAuthentication) · Teams (CreateTeamInvitation, DeleteTeam, RespondToTeamInvitation, SaveTeam, UpdateTeamMember).
- **Http/Responses** : LoginResponse, TwoFactorLoginResponse, VerifyEmailResponse (+ Concerns/RedirectsToCurrentTeam).
- **Models** : `User`, `Team` (SoftDeletes, slug auto), `Membership`, `TeamInvitation`.
- **Policies** : `TeamPolicy` (basée permissions). **Rules** : `TeamName`, `UniqueTeamInvitation`, `ValidTeamInvitation`.
- **Providers** : `AppServiceProvider` (CarbonImmutable, prohibitDestructiveCommands, Password::defaults), `FortifyServiceProvider` (vues auth Inertia, réponses custom, rate limiter login 5/min).
- **Notifications** : `Teams/TeamInvitation`.
- Aucune entité métier Faucon (workflow/node/execution) — conforme à l'attendu.

### routes/

- `web.php` : accueil (`Route::inertia('/', 'Welcome')`), groupe `auth` + `verified` + `EnsureTeamMembership` sous préfixe `{current_team}` (uniquement `dashboard`), invitations (accept/decline) sous `auth`.
- `settings.php` : profil, sécurité (mot de passe, `throttle:6,1`), apparence, équipes CRUD + membres + invitations + switch/leave.
- `api.php` : `/user` (sanctum) uniquement. `console.php` : purge quotidienne des invitations expirées.
- **21 routes applicatives** au total. Écart vs prompt de phase : seules `dashboard` est sous `{current_team}` — équipes/profil restent sous `settings/...`.

### database/

- Migrations (6) : `users` (+ password_reset_tokens + sessions), `cache`, `jobs`, `teams` (+ `team_members` + `team_invitations`), `current_team_id` sur users, `personal_access_tokens`.
- Factories : `UserFactory` (configure() → équipe personnelle + Owner + switch), `TeamFactory` (état `personal()`), `TeamInvitationFactory` (états `accepted()`, `expired()`).
- **Écart détecté** : `UserFactory` écrit les colonnes 2FA (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) mais **aucune migration ne les crée** → 74 tests rouges.

### config/

Standard starter kit : `fortify.php` (features = resetPasswords + emailVerification ; **2FA non activée**), `inertia.php`, `sanctum.php` (copie vendor), `queue/cache/mail/session` par défaut `database/database/log/database`. `.env` : APP_NAME=Laravel (générique), APP_URL=http://localhost:8000.

### tests/ — 14 fichiers Pest

- Feature : Auth (Authentication, EmailVerification, PasswordReset, VerificationNotification), Dashboard, Settings (ProfileUpdate, Security), Teams (Team, TeamMember, TeamInvitation, PruneExpiredTeamInvitations), ExampleTest (smoke accueil `route('home')`).
- Unit : ExampleTest. Helpers : `TestCase::skipUnlessFortifyHas()`.
- Aucun test de registration (feature Fortify non activée) ni de 2FA active (tests 2FA présents mais skip).
- **82 tests, état AVANT : 5 passed, 74 errors, 3 skipped** — tous les échecs = colonnes 2FA manquantes.

## 3. Inventaire frontend (`resources/js/`)

- **Généré Wayfinder** (ne pas éditer) : `actions/`, `routes/`, `wayfinder/` — synchronisés (régénération sans diff).
- **Composants** : 30 composants applicatifs (AppShell, AppSidebar, TeamSwitcher, modales équipes/invitations…) + kit `components/ui/` shadcn-vue (23 familles, exclus du lint/fmt).
- **Composables** : `useAppearance`, `useCurrentUrl`, `useInitials`. **Lib** : `utils.ts` (`cn`), `flashToast.ts`.
- **Layouts** : `AppLayout`, `AuthLayout`, `settings/Layout`, `app/{AppHeaderLayout,AppSidebarLayout}`, `auth/{AuthCardLayout,AuthSimpleLayout,AuthSplitLayout}`.
- **Pages** : `Welcome`, `Dashboard`, `auth/{Login,ForgotPassword,ResetPassword,VerifyEmail}`, `settings/{Profile,Security,Appearance}`, `teams/{Index,Edit}`.
- **Types** : `auth.ts`, `navigation.ts`, `teams.ts`, `ui.ts`, barrel `index.ts`, shims.
- Point d'entrée `app.ts` : résolution de layout par préfixe de page ; props partagées : `name`, `auth.user`, `sidebarOpen`, `currentTeam`, `teams`.
- Build Vite : ✅ (5.4 s). Aucun écran métier — conforme.

## 4. Chaîne qualité — état AVANT correctifs

| Outil | État avant | Détail |
|---|---|---|
| Pint | 🔴 1 fichier | `bootstrap/app.php` — fins de ligne mixtes CRLF/LF (`line_ending`) |
| PHPStan (Larastan L7) | 🔴 1 erreur | `config/sanctum.php:21` — `explode()` sur `bool|string` (`argument.type`, code identique à la config vendor) |
| Pest | 🔴 74 / 82 | Tous échecs : colonnes 2FA absentes de `users` (factory les écrit) |
| Build Vite (`npm run build`) | ✅ | 5.4 s |
| Wayfinder | ✅ | Régénération sans diff |
| oxlint/vue-tsc (`npm run check`, `npm run types:check`) | ✅ | 0 erreur |

## 5. Correctifs de socle appliqués (audit → correction)

Voir `report.md` section Implementation pour la liste finale des fichiers — en résumé :

1. **Migration d'ajout des colonnes two-factor à `users`** (Fortify standard : `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, toutes nullables) → débloque les 74 tests.
2. **`App\Models\User` implémente `MustVerifyEmail`** — requis par `Features::emailVerification()` (activée), le middleware `verified` (utilisé) et `EmailVerificationTest`.
3. **`config/sanctum.php`** : calcul typé-safe du défaut `stateful` (garde `is_string`) → PHPStan 0 erreur, sans ignore ni baseline.
4. **Pint** : normalisation des fins de ligne (`bootstrap/app.php`).
5. **`config/database.php`** : retrait de la ligne parasite `-- Active: …` avant `<?php` (tampon d'outil BDB arrivé avec la bascule externe vers MySQL — émettait une sortie avant les headers HTTP). Le changement intentionnel du défaut MySQL (`laravel` → `faucon`) est conservé ; l'écart de style concat a été re-normalisé par Pint.

Correctifs volontairement **hors périmètre** (notés, non appliqués) : activation 2FA (phase 2), publication migrations passkeys (quand câblées), `php artisan storage:link` (aucun usage disque public à ce jour), `APP_NAME=Faucon` dans `.env` (local uniquement), renommage `ExampleTest` → `HomeTest` (suppression/renommage de tests soumis à validation).

## 6. Écarts constatés vs hypothèses du prompt de phase

| Hypothèse phase1.md | Réalité constatée | Décision |
|---|---|---|
| Base MySQL possible | Départ : SQLite partout. En **cours d'audit, `.env` a été basculé vers MySQL** (`DB_CONNECTION=mysql`, base `faucon`) avec une ligne parasite d'outil BDD (`-- Active: …`) insérée avant `<?php` de `config/database.php` — ligne retirée (corrompt la sortie HTTP) ; le défaut `faucon` est conservé. Tests : toujours sqlite `:memory:`. **Décision officialisée après audit : MariaDB par défaut (pilote natif `mariadb`)** (`.env.example` aligné, sqlite conservé en option) | Constat consigné : config DB-compatible multi-moteurs, tests isolés sur sqlite |
| « Vitest (front) » | Aucune spec front ; checks = oxlint + vue-tsc | Constat consigné ; Vitest quand logique front testable |
| Routes sous préfixe `{current_team}` | Seul `dashboard` est scopé `{current_team}` ; reste sous `settings/` | Constat consigné — l'architecture équipe reste solide |
| 2FA/passkeys câblés | 2FA non activée (tests skip), passkeys non câblés | Activation reportée phase 2 (consolidation auth) |
| Files d'attente « déjà migrée » | Confirmé (`jobs`, `cache`, queue=database) | RAS |
| — | `config/database.php` modifié dans le working tree (défaut MySQL `laravel`→`faucon`), hors session agent | Modifié avant l'audit, non annulé, à inclure dans le commit de l'auteur |

## 7. Structure cible & fondations

- Structure backend/frontend cible **validée comme direction** (master §6-7) : aucun dossier vide créé ; `app/Services/{AI,Workflow,Integration}`, `app/Data`, `app/Support` seront créés au premier besoin réel.
- Conventions documentées : `.knowledge/conventions.md`.
- Fondations domaine (scoping, moteur, nommage node types, glossaire, cycles de vie) : `.knowledge/domain.md` (base de connaissance locale, gitignorée).
