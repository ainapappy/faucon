# Phase 2 — Audit auth (état avant compléments)

> Audit réalisé le 2026-09-19 en début de phase 2. Source de vérité : code + `php artisan route:list` + suite Pest (82 tests : 79 passés, 3 skippés, 0 échec).

## 1. Inventaire des routes Fortify actives

| Route                                                                                                              | Nom                                   | État                                                                                                                                                           |
| ------------------------------------------------------------------------------------------------------------------ | ------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET/POST `/login`, POST `/logout`                                                                                  | `login`, `login.store`, `logout`      | ✅ actif, testé                                                                                                                                                |
| GET/POST `/forgot-password`, GET `/reset-password/{token}`, POST `/reset-password`                                 | `password.request/email/reset/update` | ✅ actif, testé (5 tests)                                                                                                                                      |
| GET `/email/verify`, GET `/email/verify/{id}/{hash}`, POST `/email/verification-notification`                      | `verification.*`                      | ✅ actif, testé (8 tests), `MustVerifyEmail` sur User (posé phase 1)                                                                                           |
| GET/POST `/user/confirm-password`, GET `/user/confirmed-password-status`                                           | `password.confirm*`                   | ⚠️ routes enregistrées mais **500** : aucun callback `Fortify::confirmPasswordView()` (contrat non instantiable) — trou latent, jamais appelé faute de lien UI |
| GET/POST `/register`                                                                                               | `register`, `register.store`          | ❌ absent : `Features::registration()` non activée                                                                                                             |
| GET/POST `/two-factor-challenge` + gestion (`two-factor.enable/confirm/disable/qr-code/secret-key/recovery-codes`) | `two-factor.*`                        | ❌ absent : feature non activée                                                                                                                                |
| `/passkeys/*` (login-options, login, confirm*, registration-options, store, destroy)                               | `passkey.*`                           | ❌ absent : feature non activée                                                                                                                                |

Routes applicatives (21) inchangées : dashboard `{current_team}`, invitations, settings (profile/security/teams), throttle `6,1` sur update password.

## 2. Features Fortify (config/fortify.php)

Actives : `resetPasswords()`, `emailVerification()`. Absentes : `registration()`, `twoFactorAuthentication()`, `passkeys()`.

## 3. Actions / réponses / provider

- `app/Actions/Fortify/` : `ResetUserPassword` uniquement. Pas de `CreateNewUser`.
- Réponses custom : `LoginResponse` ✅ bindée, `VerifyEmailResponse` ✅ bindée, `TwoFactorLoginResponse` écrite mais **non bindée**.
- `FortifyServiceProvider` : vues login/reset/forgot/verify ; rate limiter `login` 5/min (email+IP) ✅. Callbacks manquants : `registerView`, `confirmPasswordView`, `twoFactorChallengeView`.

## 4. Modèle User

`MustVerifyEmail` ✅, `HasTeams` ✅, casts `email_verified_at`/`password` ✅, `#[Fillable]`/`#[Hidden]` ✅. Colonnes 2FA présentes (migration phase 1), factory states `unverified()`/`withTwoFactor()` ✅. Traits manquants : `TwoFactorAuthenticatable`, `PasskeyAuthenticatable` (+ contrat `PasskeyUser`).

## 5. Sécurité vérifiée

- Throttling login : `RateLimiter::for('login')` 5/min par `email|ip` translittéré ✅ (testé).
- Mot de passe : `PasswordValidationRules` (`Password::default()` + `confirmed`) ; défauts renforcés en production via `AppServiceProvider` (12 chars, mixedCase, chiffres, symboles, uncompromised) ✅. Update password exige `current_password` + `throttle:6,1` ✅ (testé).
- Routes protégées : dashboard sous `auth` + `verified` + `EnsureTeamMembership` ✅ ; settings sous `auth`, destructions sous `auth` + `verified` ✅.
- Sessions : driver database, invalidate + regenerateToken sur logout/suppression de compte ✅. CSRF : groupe `web` par défaut ✅.
- Props Inertia : aucune donnée sensible (pas de secrets — vérifié `HandleInertiaRequests`).
- API : `routes/api.php` = `/user` sous `auth:sanctum` uniquement (surface minimale).
- Confirmation password : trou latent 500 (cf. §1) — corrigé en phase 2 (callback + page).

## 6. Couverture Pest (parcours auth)

| Parcours                                                             | État                                                                              |
| -------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| Login OK / mauvais password / throttling / logout                    | ✅ `AuthenticationTest` (6 tests dont 1 skippé 2FA)                               |
| Reset password (rendu, envoi, succès, token invalide)                | ✅ `PasswordResetTest`                                                            |
| Vérification email (rendu, hash/id invalide, déjà vérifié, redirect) | ✅ `EmailVerificationTest` + `VerificationNotificationTest`                       |
| Inscription                                                          | ❌ aucun (feature absente)                                                        |
| 2FA (challenge TOTP, recovery codes, gestion)                        | ❌ 3 tests skippés, aucune couverture réelle                                      |
| Profil (update avec re-vérification, suppression avec password)      | ✅ `ProfileUpdateTest`                                                            |
| Update password (succès, current_password requis)                    | ✅ `SecurityTest` (partiel : 2 tests skippés sur props 2FA)                       |
| Policies « non-membre ne peut rien faire »                           | ❌ couverture indirecte uniquement (équipes : 42 tests membres/rôles/invitations) |

## 7. Passkeys

`laravel/passkeys` 0.2.1 installé (dépendance de Fortify 1.39) : migration non publiée, config non publiée (inutile : Fortify réécrit `passkeys.*` au boot), trait/contrat absents du User, aucun test. Décision utilisateur (2026-09-19) : **intégration complète**, incluant la dépendance npm officielle `@laravel/passkeys` (seule dépendance nouvelle autorisée).

## 8. Trous retenus → compléments de phase

1. Registration (feature + action + vue + réponse custom → vérification email).
2. 2FA complète (trait, feature `confirm`+`confirmPassword`, binding `TwoFactorLoginResponse`, vues challenge + ConfirmPassword, props SecurityController, UI settings) — dé-skippage des 3 tests.
3. Passkeys (migration, trait + contrat, feature, `PasskeyLoginResponse` team-aware, props Security, UI login + settings) — surface testée sans cérémonie WebAuthn navigateur.
4. Test direct `TeamPolicy` (dont « non-membre ne peut rien faire ») + pattern d'autorisation documenté (`.knowledge/decisions/authorization.md`).
5. Scoping team-scoped : déjà tranché phase 1 (`.knowledge/domain.md` §1) — rappel consigné dans `authorization.md`.

## 9. Révision (2026-09-19) — reprise de la phase 2

Relecture des livrables de la phase 2 après la formalisation des conventions (`.knowledge/conventions.md` : namespace `Ainatrix`, SCSS, casse `Ai`) et la reprise de la phase 1. Aucun correctif code nécessaire — les livrables étaient déjà conformes. Aucune fonctionnalité, aucune dépendance.

**Chaîne qualité ré-exécutée — intégralement verte :**

| Outil                 | État        | Détail                                                   |
| --------------------- | ----------- | -------------------------------------------------------- |
| Pint (`--dirty`)      | ✅ passed   | Arbre propre, 0 fichier à corriger                       |
| PHPStan (Larastan L7) | ✅ 0 erreur | Périmètre incluant `ainatrix/`                           |
| Pest                  | ✅ 121/121  | 423 assertions, 0 skip                                   |
| `npm run check`       | ✅          | oxfmt 80 fichiers, oxlint 68 fichiers 0 warning          |
| `npm run types:check` | ✅          | vue-tsc 0 erreur                                         |
| Build Vite            | ✅          | Succès                                                   |
| Wayfinder             | ✅          | Régénération idempotente, aucun diff (routes inchangées) |

**Conformité des livrables vérifiée :**

- Backend : `#[Fillable]`/`#[Hidden]` (attributs natifs Laravel 13), `casts(): array`, PHPDoc `@property` complet et array shapes (`SecurityController::securityProps`), types explicites partout, réponses Fortify dans `app/Http/Responses/` avec concern `RedirectsToCurrentTeam`, rate limiter `login` dans `FortifyServiceProvider` — conforme à `conventions.md` §1-2.
- Frontend : ordre SFC (script → template), un seul élément racine, aucun bloc `<style>` hors kit `ui/`, attributs `data-test`, types dans `types/` (barrel), imports Wayfinder `@/routes/...`, toasts `vue-sonner` — conforme à `conventions.md` §5. Le SCSS introduit après la phase (`resources/scss/app.scss`) n'entre pas en conflit : aucun livrable phase 2 ne porte de style custom.
- Tests : style fonctions Pest, factories systématiques, aucune création manuelle de modèles — conforme à `conventions.md` §4.
- Documentation : `vp check` vert (Markdown inclus) ; `.knowledge/decisions/authorization.md` présent (scoping team-scoped + gabarit `{Ressource}Policy`).

**Observation laissée en attente de décision :** `.knowledge/prompts/phase6.md` cite encore `app/Services/AI/**` et les DTOs `AIRequest`/`AIResponse` (casse `AI`), alors que la convention actuelle impose `Ai` (`conventions.md` §2, `domain.md` §3, README corrigé lors de la reprise de la phase 1). Prompt master non modifié sans validation utilisateur ; à trancher avant d'exécuter la phase 6.
