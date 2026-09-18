# Phase 1 — Architecture & Foundations

## Summary

Audit complet du squelette existant (Laravel 13.32 / PHP 8.4.4 / Inertia v3 / Fortify 1.39 / système d'équipes complet, aucune fonctionnalité métier Faucon), consignation des versions réelles, documentation des conventions effectivement en vigueur et des fondations du domaine (scoping team-scoped, emplacement du moteur, nommage des node types, glossaire, cycles de vie). Remise en vert de la chaîne qualité : migration Fortify des colonnes 2FA manquantes (débloquant 74 tests rouges), implémentation de `MustVerifyEmail` sur `User` (trou silencieux du middleware `verified`), correction type-safe de `config/sanctum.php` (PHPStan), normalisation Pint, retrait d'une ligne parasite avant `<?php` dans `config/database.php`. Aucune fonctionnalité métier développée, aucune dépendance ajoutée, aucune route modifiée.

## Implementation

### Files Created

- `database/migrations/2026_09_18_181750_add_two_factor_columns_to_users_table.php` — colonnes Fortify 2FA (`two_factor_secret` text nullable, `two_factor_recovery_codes` text nullable, `two_factor_confirmed_at` timestamp nullable), modèle : migration vendor Fortify 1.39 ; `down()` droppe les 3 colonnes.
- `.knowledge/conventions.md` — conventions PHP / structure / BDD / Pest / Vue-TS / qualité / git, documentées depuis le code réel (fichier gitignoré, base de connaissance locale).
- `.knowledge/domain.md` — décisions de fondation : scoping team-scoped, `app/Services/Workflow/**` (découpage cible du moteur), nommage `{category}.{type}`, glossaire, modèle métier, cycles de vie workflow (`draft|active`) et exécution (`pending|running|completed|failed|cancelled`) (gitignoré).
- `docs/reports/phase1/audit.md` — rapport d'audit (versions, inventaires, routes, état qualité avant/après, écarts vs hypothèses).
- `docs/reports/phase1/report.md` — le présent rapport.

### Files Modified

- `app/Models/User.php` — décommente l'import et ajoute `implements MustVerifyEmail` (requis par `Features::emailVerification()` activée, le middleware `verified` et `EmailVerificationTest`). Aucun autre changement (trait `TwoFactorAuthenticatable` : phase 2).
- `config/sanctum.php` — calcul du défaut `stateful` extrait dans une variable + garde `is_string(...)` : corrige l'erreur PHPStan `argument.type` sans ignore ni baseline, comportement préservé.
- `bootstrap/app.php` — reformaté par Pint uniquement (fins de ligne mixtes CRLF/LF).
- `config/database.php` — retrait de la ligne parasite `-- Active: 1769436587316@@127.0.0.1@3306@faucon` insérée avant `<?php` par un outil BDD lors de la bascule externe du `.env` vers MySQL (elle émettait une sortie avant les headers HTTP). Le changement intentionnel du défaut MySQL (`laravel` → `faucon`) présent dans le working tree est conservé ; l'écart de style concat a été re-normalisé par Pint.

### Database Changes

- Une migration : colonnes 2FA sur `users` (ci-dessus), appliquée en dev (batch 2).
- État DB : **le `.env` a été basculé vers MySQL** (`DB_CONNECTION=mysql`, base `faucon@127.0.0.1:3306`) pendant la session d'audit — modification externe conservée. **Décision officialisée à l'issue de la phase (2026-09-18) : MariaDB est le moteur de dev par défaut, via le pilote natif `mariadb`** — `.env.example` aligné (variants `mysql` et `sqlite` documentés en commentaire), défaut `faucon` harmonisé sur les blocs `mysql` et `mariadb` de `config/database.php`. Les tests restent sur sqlite `:memory:` (phpunit.xml, isolés et sans serveur). Le schéma est portable (migrations standard Blueprint).

### Routes

- Aucune modification. 21 routes applicatives inchangées ; `wayfinder:generate` régénère `resources/js/actions` + `resources/js/routes` sans aucun diff.

### Frontend Changes

- Aucun changement de code. Build Vite vérifié (✅ ~5 s). Checks front : `npm run check` (oxlint type-aware, denyWarnings) ✅, `npm run types:check` (vue-tsc) ✅.

### Tests

- Suite : **82 tests — 79 passés, 3 skippés (2FA feature-gated via `skipUnlessFortifyHas`), 0 échec, 253 assertions** (`php artisan test --compact`). Avant phase : 5 passés / 74 erreurs.
- Les 3 skippés : `SecurityTest` ×2 + `AuthenticationTest` (login avec challenge 2FA) — sautent car `Features::twoFactorAuthentication()` n'est pas activé (activation volontairement reportée phase 2).
- TDD : Red documenté (`EmailVerificationTest` : 6/6 en échec sur colonnes manquantes) → Green après migration, périmètre maintenu vert après chaque correctif.

### Commands Executed

- Inventaire : `application-info` (Boost), `composer show --direct`, `php artisan about`, `php artisan route:list --except-vendor`, `git ls-files --eol`.
- État avant : `vendor/bin/pint --test` (1 fichier), `vendor/bin/phpstan analyse --no-progress` (1 erreur), `php artisan test --compact` (74 erreurs), `npm run build` ✅, `php artisan wayfinder:generate` (sans diff).
- Correctifs : `php artisan make:migration add_two_factor_columns_to_users_table --table=users --no-interaction`, `vendor/bin/pint --format agent`, `php artisan migrate --no-interaction`, sed retrait ligne parasite.
- État après : `vendor/bin/pint --test` ✅ · `vendor/bin/phpstan analyse --no-progress` 0 erreur · `php artisan test --compact` 79/82 (+3 skippés) · `npm run build` ✅ · `php artisan config:show database.default` → mysql.

## Key Information

### Technical Decisions

1. **Scoping team-scoped** pour toutes les ressources métier (Workflow, nodes, edges, exécutions, logs, Integration, Template) : cohérent avec `EnsureTeamMembership` + préfixe `{current_team}` ; la personal team (`is_personal`) couvre l'espace privé → le scoping user est redondant ; le partage (KPIs par équipe, templates) est un besoin de première classe. Détail et alternatives écartées : `.knowledge/domain.md` §1.
2. **Moteur** : `app/Services/Workflow/**` — `WorkflowRunner`, `GraphValidator`, `GraphTraverser`, `NodeHandlerRegistry` (unique point d'extension), `ExecutionContext`, `Handlers\{Category}\{Type}Handler`. Frontière Actions (intentions HTTP/queue) / moteur (appelable depuis un job). Rien créé d'avance — naissance au besoin, TDD, phases 4-8.
3. **Node types** : `{category}.{type}` string en snake_case (`trigger.manual`, `data.http_request`, `ai.prompt`…), projection mécanique `Handlers\{Category}\{Type}Handler` ; pas d'enum PHP (double source de vérité, casserait l'extension sans toucher au moteur). Segment `Ai` (pas `AI`).
4. **Cycles de vie** : workflow `draft|active` (2 états volontairement) ; exécution `pending|running|completed|failed|cancelled`, transitions fermées, états terminaux immuables ; logs append-only. `.knowledge/domain.md` §4.
5. **Écart structure master vs réel** : dossiers front en minuscules + `lib/` (et non `Components/`, `Utils/`) — le code existant gagne ; documenté dans `.knowledge/conventions.md` §5.
6. **Migration 2FA sans activer la feature** : le schéma rattrape ce que le code (factory, model, Hidden) référence déjà ; l'activation de `Features::twoFactorAuthentication()` reste un choix de phase 2.

### Gotchas & Solutions

- **74 tests rouges d'un seul tenant** : la factory écrit les colonnes 2FA absentes du schéma → migration Fortify (copie conforme vendor). Red→Green documenté.
- **Trou silencieux `MustVerifyEmail`** : `Illuminate\Foundation\Auth\User` embarque le trait `MustVerifyEmail`, donc `hasVerifiedEmail()` existait à l'exécution — mais sans l'interface, le middleware `verified` laissait passer les non-vérifiés (`instanceof` faux). Aucun test ne le couvrait ; l'interface corrige le contrôle d'accès.
- **Ligne parasite avant `<?php`** dans `config/database.php` (tampon d'outil BDD arrivé avec la bascule externe du `.env`) : émise en sortie à chaque chargement de config → corromprait les réponses HTTP. Retraitée en conservant le changement intentionnel (`faucon`).
- **`.env` modifié en cours de session** (sqlite → mysql) : modification externe conservée ; tests insensibles (sqlite `:memory:` forcé par phpunit.xml) ; consigné dans l'audit.
- **Outils MCP Boost indisponibles transitoirement** pendant la session back-dev (`Invalid JSON output`) : contournés par `composer show`, migrations vendor Fortify, lecture du code. À surveiller/redémarrer si récurrent.

### Commands & Config

- Chaîne qualité de référence : `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse --no-progress` → `php artisan test --compact` → `npm run build` (+ `php artisan wayfinder:generate` si routes modifiées). `composer test` enchaîne lint+phpstan+tests.
- Aucune variable `.env` ajoutée/modifiée par la phase ; `.env.example` inchangé (aucune nouvelle config introduite).
- Config notable : `database.default` = mysql (dév, choix utilisateur), `queue`/`cache`/`session` = database, `mail` = log ; fortify features = resetPasswords + emailVerification uniquement.

### Version Notes

- Laravel 13 : mass assignment par attributs natifs `#[Fillable([...])]` / `#[Hidden([...])]` (remplace `$fillable`/`$hidden`) — convention du projet.
- Fortify 1.39 : migration 2FA embarquée dans `vendor/laravel/fortify/database/migrations/` (référence utilisée) ; `Features::enabled()` + helper `skipUnlessFortifyHas()` pour les tests feature-gated.
- Front : tooling **vite-plus** (`vp build/dev/check`) — Vitest présent transitivement mais aucune spec front ; checks front = oxlint type-aware + vue-tsc.
- Sanctum 4.3.3 : la config publiée contient le même code `explode(env(...))` que la vendor — l'erreur PHPStan vient du niveau 7 sur `config/`, corrigée localement par garde `is_string`.

## Future Ideas (Not Planned)

- **Renommer `ExampleTest` → `HomeTest`** (et l'unit en un test utile) : les noms « Example » sont des résidus du starter kit. Intéressant pour la lisibilité du portfolio ; à évaluer en phase 12 (tests) — suppression/renommage de tests soumis à validation.
- **CI/CD GitHub Actions** (lint + phpstan + pest + build à chaque push) : la chaîne qualité est désormais entièrement scriptable ; pertinent dès que le repo est public.
- **`php artisan storage:link`** à créer quand une fonctionnalité exposera des fichiers publics (aucun usage à ce jour).
- **`APP_NAME=Faucon`** dans `.env` / `.env.example` + `VITE_APP_NAME` : cosmétique, impacte titres Inertia ; à faire à la première passe UX (phase 10).
- **Monitoring d'erreurs (Sentry) et environnement Docker de dev** : utiles à l'échelle du portfolio ; à évaluer après les phases moteur (7-8).

## Known Limitations

- La 2FA reste désactivée (3 tests skippés) : schéma et factory prêts, activation en phase 2.
- Pas de registration (feature Fortify non activée) : les comptes viennent des factories/seeders — phase 2.
- Passkeys : package installé mais non câblé (migrations non publiées) — phase 2.
- `.knowledge/` est gitignoré : conventions et décisions de domaine ne sont pas dans le repo (base de connaissance locale) ; `docs/reports/phase1/` est committable et fait foi.
- Toute la suite Pest couvre le socle (auth, équipes, dashboard) ; aucune couverture métier workflow (normal : rien à couvrir en phase 1).

## Next Phase

**Phase 2 — Authentication & Users** (consolidation Fortify, policies, base d'autorisation) — à ne commencer qu'après validation explicite de la phase 1 (master §37). Points d'entrée préparés : activation 2FA + dé-skippage des tests feature-gated, publication des migrations passkeys, registration.
