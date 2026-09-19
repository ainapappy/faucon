# Phase 2 — Authentication & Users

## Summary

Audit complet de la couverture auth réelle (consigné dans [audit.md](audit.md)) puis comblement des trous identifiés, sans toucher aux parcours existants : registration Fortify (action `CreateNewUser`, réponse custom redirigeant vers la vérification email), 2FA TOTP complète (trait, feature `confirm`+`confirmPassword`, binding `TwoFactorLoginResponse`, vues challenge et confirmation password, props Security, UI de gestion complète), passkeys de bout en bout (migration, contrat `PasskeyUser` + trait, réponse de login team-aware, UI login et settings via `@laravel/passkeys`), correction du 500 latent de `GET /user/confirm-password` (callback `confirmPasswordView` manquant), tests directs de `TeamPolicy` (dont « un non-membre ne peut rien faire ») et formalisation du pattern d'autorisation des futures ressources team-scoped (`.knowledge/decisions/authorization.md`). TDD Pest sur chaque complément. Suite : 82 tests (79 passés, 3 skippés) → **121 tests, 121 passés, 0 skip**.

## Implementation

### Files Created

Backend :

- `app/Actions/Fortify/CreateNewUser.php` — implémente `Laravel\Fortify\Contracts\CreatesNewUsers` ; validation name/email (lowercase, unique)/password (`PasswordValidationRules`) ; `User::create`.
- `app/Http/Responses/RegisterResponse.php` — contrat Fortify ; `wantsJson()` → 201, sinon redirect `verification.notice` (parcours exigé « inscription → vérification email »).
- `app/Http/Responses/PasskeyLoginResponse.php` — contrat `Laravel\Passkeys\Contracts\PasskeyLoginResponse` ; trait `RedirectsToCurrentTeam` ; JSON `{redirect}` ou redirect intended.
- `database/migrations/2026_09_18_215959_create_passkeys_table.php` — migration vendor publiée (`--tag=passkeys-migrations`, re-datée par le framework) ; table `passkeys` (user_id FK cascade, name, credential_id unique, credential json, last_used_at).
- Tests : `tests/Feature/Auth/RegistrationTest.php` (6), `tests/Feature/Auth/TwoFactorChallengeTest.php` (6), `tests/Feature/Auth/PasskeyAuthenticationTest.php` (4), `tests/Feature/Settings/TwoFactorAuthenticationTest.php` (9), `tests/Feature/Settings/PasskeyManagementTest.php` (8), `tests/Feature/Teams/TeamPolicyTest.php` (6) — 39 tests, TDD Red→Green par lot.

Frontend :

- `resources/js/pages/auth/Register.vue` — `<Form>` POST register, InputError par champ, Spinner+disabled pendant processing, `data-test="register-button"`.
- `resources/js/pages/auth/TwoFactorChallenge.vue` — bascule client code TOTP ↔ recovery code, `autocomplete="one-time-code"`, erreurs `errors.code`/`errors.recovery_code` selon le mode.
- `resources/js/pages/auth/ConfirmPassword.vue` — `<Form>` POST confirmation password, PasswordInput `current-password`.
- `resources/js/components/TwoFactorSettings.vue` — machine à états off/confirming/on (enable → QR + secret + confirm → codes de récupération + disable), QR/secret/codes via requêtes JSON natives, confirm en `<Form>` avec `error-bag="confirmTwoFactorAuthentication"`.
- `resources/js/components/PasskeySettings.vue` — liste (nom + dernière utilisation), ajout (dialog nom → `usePasskeyRegister`), suppression (dialog confirmation → DELETE, toasts vue-sonner + reload), gating support WebAuthn.

Documentation :

- `docs/reports/phase2/audit.md` — audit auth avant compléments (routes, features, sécurité, couverture, trous retenus).
- `docs/reports/phase2/report.md` — le présent rapport.
- `.knowledge/decisions/authorization.md` (gitignoré) — scoping team-scoped rappelé + gabarit `{Ressource}Policy` (type WorkflowPolicy) pour les phases 3+.

### Files Modified

- `app/Models/User.php` — `implements MustVerifyEmail, PasskeyUser` (alias Fortify) ; traits `TwoFactorAuthenticatable` + `PasskeyAuthenticatable` ; PHPDoc `@property-read Collection<int, Passkey> $passkeys`.
- `app/Providers/FortifyServiceProvider.php` — bindings `RegisterResponse`, `TwoFactorLoginResponse` (existe depuis avant, était orphelin), `PasskeyLoginResponse` ; `Fortify::createUsersUsing(CreateNewUser::class)` ; vues `registerView`, `twoFactorChallengeView`, `confirmPasswordView` (cette dernière corrige le 500 latent de `GET /user/confirm-password`).
- `app/Http/Controllers/Settings/SecurityController.php` — `edit()` délègue à `securityProps()` : `ensureStateIsValid()` (machine à états 2FA, appelée par personne d'autre dans Fortify), props `passwordRules`, `canManageTwoFactor` (dynamique), `twoFactorEnabled`, `requiresConfirmation`, `canManagePasskeys`, `passkeys[{id,name,lastUsedAt}]` ; PHPDoc array shape complet.
- `config/fortify.php` — features finales : `registration()`, `resetPasswords()`, `emailVerification()`, `twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`, `passkeys(['confirmPassword' => true])`.
- `resources/js/pages/auth/Login.vue` — divider + bouton « Log in with a passkey » (`usePasskeyVerify`, masqué si non supporté, `router.visit(response.redirect)` au succès).
- `resources/js/pages/settings/Security.vue` — sections 2FA (`TwoFactorSettings`) et passkeys (`PasskeySettings`) rendues selon `canManageTwoFactor`/`canManagePasskeys` ; props étendues au contrat.
- `resources/js/types/auth.ts` — type `Passkey` (barrel `types/index.ts`).
- `package.json` / `package-lock.json` — `@laravel/passkeys@0.4.0` (seule dépendance nouvelle, approuvée par l'utilisateur ; embarque `@simplewebauthn/browser`).
- `tests/Feature/Auth/AuthenticationTest.php` — dé-skip du test 2FA (bloc `markTestSkipped` + appel runtime `Features::twoFactorAuthentication` retirés).
- `tests/Feature/Settings/SecurityTest.php` — dé-skip des 2 tests props (le test « feature disabled » garde son `config(['fortify.features' => []])`).
- `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/TwoFactorChallengeTest.php`, `tests/Feature/Settings/PasskeyManagementTest.php` — resserrage post-front : `withoutVite()` et `component(name, false)` retirés (pages existantes, rendu complet vérifié).
- `README.md`, `docs/reports/phase1/audit.md` — formatage oxfmt uniquement (`vp check` couvre le Markdown ; échec préexistant à la phase).

### Database Changes

- Une migration : `passkeys` (vendor laravel/passkeys publiée), appliquée en dev (MariaDB). Schéma tests : sqlite `:memory:` via RefreshDatabase (aucun changement).
- Aucune autre modification ; aucune variable `.env` ajoutée.

### Routes

- Aucune route applicative ajoutée. Activation de routes vendor Fortify par features : `GET/POST /register` (guest) ; `GET/POST /two-factor-challenge` + gestion 2FA (`enable/confirm/disable/qr-code/secret-key/recovery-codes`, middleware `auth` + `password.confirm`) ; passkeys (`login-options`/`login` guest, `confirm-options`/`confirm`/`registration-options`/`store`/`destroy` auth + `password.confirm`).
- `php artisan wayfinder:generate` : nouveaux modules `resources/js/routes/{register,two-factor,passkey,password}` + actions vendor (`@/actions/Laravel/Fortify`, `@/actions/Laravel/Passkeys`).

### Frontend Changes

- 3 pages auth créées, 1 page modifiée (passkey), 2 composants settings créés, 1 page settings étendue, 1 type ajouté.
- Canaux respectés : `<Form>` pour les soumissions HTML (suit les redirects — un `password.confirm` expiré déclenche la visite pleine page ConfirmPassword, non interceptée, `redirect()->intended` ramène ensuite) ; fetch JSON (`Accept: application/json`) pour qr-code/secret-key/recovery-codes ; hooks `@laravel/passkeys/vue` pour les cérémonies WebAuthn (gèrent `{redirect}` eux-mêmes).
- État « confirming » 2FA porté côté client (ref local après enable réussi quand `requiresConfirmation`) — jamais déduit des props, car le serveur auto-disable une 2FA jamais confirmée au rechargement (`ensureStateIsValid`).
- Vérifications : `npm run check` 67/67 fichiers 0 warning, `npm run types:check` 0 erreur, `npm run build` succès.

### Tests

- **121 tests, 121 passés, 423 assertions, 0 skip** (avant phase : 82 tests, 79 passés, 3 skippés, 253 assertions). `+39` tests / `+170` assertions.
- Parcours couverts : registration (rendu, succès → vérification email, validation, lowercase, notification VerifyEmail, redirect authentifié) ; 2FA challenge (rendu, redirect sans session, TOTP valide/invalide, recovery code, remplacement du code consommé) ; gestion 2FA (enable avec `password.confirm` exigé, confirm valide/invalide, disable, QR, secret, codes GET/régénération) ; passkeys (options guest, gating auth, payload invalide, session expirée, registration options + `password.confirm`, suppression propre/403 autrui, props Security) ; TeamPolicy directe (non-membre ne peut rien faire — 8 abilities, mapping Owner/Member, personal delete/leave, viewAny).
- TDD : Red documenté par lot, Green avant lot suivant ; `skipUnlessFortifyHas` conservé dans `tests/TestCase.php` comme garde générique (plus aucun skip actif).

### Commands Executed

- Inventaire/protocole : `composer show --direct`, `php artisan route:list --except-vendor/--only-vendor`, `search-docs` (Fortify : registration, 2FA confirm, passkeys confirmPassword, password confirmation), lecture vendor (`laravel/fortify`, `laravel/passkeys`) avant toute écriture.
- Génération : `php artisan make:test --pest` ×6, `php artisan make:class` ×3, `php artisan vendor:publish --tag=passkeys-migrations --no-interaction`, `php artisan migrate --no-interaction`, `php artisan wayfinder:generate` (×2 — après back, contrôle final), `npm install @laravel/passkeys`.
- Qualité : `vendor/bin/pint --dirty --format agent` (passed), `vendor/bin/phpstan analyse --no-progress` (0 erreur, niveau 7 sans ignore), `php artisan test --compact` (121/121), `npm run build`, `npm run check`, `npm run types:check`, `npx vp check --fix` (formatage Markdown de 3 fichiers docs/README).

## Key Information

### Technical Decisions

1. **Registration via Fortify** (`Features::registration()` + contrat `CreatesNewUsers`) : pattern existant (cf. `ResetUserPassword`), zéro contrôleur maison. `RegisterResponse` custom → `verification.notice` : la réponse vendor redirigerait vers le dashboard bloqué par `verified`.
2. **2FA `confirm` + `confirmPassword`** : flux enable → QR → confirm (auto-disable si jamais confirmé) ; gestion protégée par `password.confirm`. Options alignées sur les tests préexistants (skippés → verts sans modification d'assertions).
3. **Passkeys intégrées via Fortify** : Fortify 1.39 enregistre lui-même toutes les routes passkey et appelle `Laravel\Passkeys\Passkeys::ignoreRoutes()` (`configurePasskeys`) — les routes du package publié ne sont jamais chargées. Point de réglage réel : `config/fortify.php` (section `passkeys` à défauts corrects).
4. **`config/passkeys.php` non publié** (tag `passkeys-config`) : Fortify réécrit chaque clé `passkeys.*` au boot depuis `fortify.passkeys.*`/`app.url`/`app.key` — une config publiée serait morte. `user_handle_secret` = `app.key` par défaut, aucune nouvelle variable `.env`.
5. **`PasskeyLoginResponse` team-aware** : le vendeur renverrait vers `/dashboard` (404, la route vit sous `/{current_team}/dashboard`) ; la réponse custom réutilise `RedirectsToCurrentTeam` et retourne `{redirect}` en JSON. Écart à la spec d'architecte : le contrat est `Laravel\Passkeys\Contracts\PasskeyLoginResponse` (vérifié vendor — n'existe pas côté Fortify) ; modèle via les alias Fortify `PasskeyUser`/`PasskeyAuthenticatable`.
6. **`ensureStateIsValid()` appelé par l'app** (`SecurityController`) : personne ne l'invoque dans Fortify — sans cet appel, la machine à états « confirm » (et l'auto-disable) ne tourne jamais.
7. **Surface de test passkeys sans navigateur** : gating des routes, validation des payloads, expiration de session, garde propriétaire 403, props. La crypto WebAuthn reste dans la suite du package (choix assumé, cf. Known Limitations).
8. **TeamPolicy testée directement, pattern documenté seulement** : aucun modèle Workflow en phase 2 — écrire une Policy exemple serait du code mort (interdit par la phase). Gabarit pour phases 3+ : `.knowledge/decisions/authorization.md`.

### Gotchas & Solutions

- **500 latent `GET /user/confirm-password`** : la route est enregistrée dès que `views: true`, mais le contrat `ConfirmPasswordViewResponse` n'est pas instantiable sans callback → 500 silencieux depuis la phase 1. Corrigé (`confirmPasswordView` + page). Bloquant pour 2FA comme passkeys (`password.confirm` sur les routes de gestion).
- **`vp check` formate aussi le Markdown** : README.md et `docs/reports/phase1/audit.md` étaient non conformes (échec préexistant à la phase) ; `npx vp check --fix` + vérif du diff (alignement de tableaux uniquement).
- **Wayfinder : POST de confirmation password absent de l'export nommé `confirm`** (fusion `Object.assign` uniquement sur l'export par défaut du fichier généré) → front importe le module par défaut (`password.confirm.store.form()`). Ne jamais éditer les fichiers générés.
- **Error bag 2FA confirm** : les erreurs de `two-factor.confirm` atterrissent dans le bag `confirmTwoFactorAuthentication` (constante Fortify) — le `<Form>` front déclare `error-bag` en conséquence.
- **Erreurs passkeys à clés pointées** : payload invalide → `credential.type`… ; session expirée → clé plate `credential` (les règles vendor passent avant). Tests assertés par clé, pas par texte.
- **Migration passkeys re-datée à la publication** (attendu `2024_01_01_000000` → `2026_09_18_215959`, comportement `publishesMigrations`) — ne pas renommer.
- **`Features::twoFactorAuthentication([...])`/`passkeys([...])` écrivent `fortify-options.*` à l'évaluation du config** : ne pas dupliquer ces appels au runtime (les anciens appels runtime des tests ont été supprimés au dé-skip).
- **`RedirectsToCurrentTeam` + tests** : les redirections post-login utilisent `URL::defaults(['current_team' => ...])` (middleware `SetTeamUrlDefaults`) — les assertions utilisent `route('dashboard')` avec la requête préparée par le framework.

### Commands & Config

- Chaîne finale verte : `vendor/bin/pint --dirty --format agent` → `vendor/bin/phpstan analyse --no-progress` (0) → `php artisan test --compact` (121/121, 0 skip) → `npm run check` / `npm run types:check` / `npm run build` → `php artisan wayfinder:generate` (sans diff final).
- Aucune variable `.env` ajoutée/modifiée. Config notable : features Fortify ci-dessus ; `passkeys.user_handle_secret` dérive d'`APP_KEY` (rotation d'APP_KEY = passkeys existantes non résolubles au login — à consigner dans un runbook le jour venu) ; `fortify.limiters.two-factor`/`passkeys` non configurés (pas de throttle dédié au-delà du login 5/min).
- Mail de vérification : driver `log` en dev (inchangé) — les tests mockent via `Notification::fake()`.

### Version Notes

- **Fortify 1.39** : intégration passkeys native (`FortifyServiceProvider::configurePasskeys` — `ignoreRoutes()` + réécriture `passkeys.*` depuis `fortify.passkeys.*`) ; options de features évaluées à l'évaluation du config (`fortify-options.*`) ; error bag `confirmTwoFactorAuthentication`.
- **laravel/passkeys 0.2.1** (dépendance de Fortify) : contrats à importer côté modèle = alias Fortify `PasskeyUser`/`PasskeyAuthenticatable` (étendent les contrats bruts, satisfont les `instanceof` vendor) ; réponse de login résolue depuis `Laravel\Passkeys\Contracts\PasskeyLoginResponse`.
- **@laravel/passkeys 0.4.0** (npm) : `usePasskeyVerify()` → `{verify, isLoading, error, isSupported}` avec `onSuccess({redirect})` ; `usePasskeyRegister()` → `{register(name), ...}` ; `UserCancelledError` pour ignorer l'annulation utilisateur ; `isSupported` résolu en `onMounted`.
- **vite-plus (`vp check`)** : lint oxlint type-aware + format oxfmt, Markdown inclus.

## Future Ideas (Not Planned)

- **Rate limiters dédiés 2FA/passkeys** (`fortify.limiters.two-factor`, throttle passkey login) : le challenge 2FA et le login passkey n'ont pas de throttle dédié (le login classique garde son 5/min). À évaluer en phase 11 (Security & Hardening).
- **Gestion des sessions/appareils** (logout des autres sessions, liste des sessions actives) : extension naturelle de la page Security ; pertinent avec les passkeys. À évaluer phase 10 (Dashboard & UX).
- **Inscription sur invitation uniquement** : la registration est ouverte ; le contexte d'invitation d'équipe existe déjà côté login. Décision produit à prendre avant l'ouverture publique.
- **Smoke E2E WebAuthn** (vrai navigateur + authenticator) : les cérémonies ne sont pas testées automatiquement ; un test E2E Playwright avec virtual authenticator serait le bon complément. À évaluer phase 12 (Testing).
- **Runbook rotation APP_KEY** (passkey user handles) : documenter une procédure de re-résolution avant toute rotation en production. À évaluer avant mise en production.

## Known Limitations

- Les cérémonies WebAuthn (création/assertion réelles) ne sont pas couvertes par la suite Pest (crypto navigateur) — seules les surfaces serveur (gating, validation, session, propriété) le sont ; l'UI gère les échecs (support absent, annulation, session expirée) mais aucun smoke réel n'a été exécuté dans cette session.
- Pas de throttle dédié sur `/two-factor-challenge` et le login passkey (cf. Future Ideas).
- La 2FA désactivée puis réactivée efface les codes de récupération précédents (comportement Fortify standard).
- `.knowledge/` reste gitignoré — les décisions (scoping, pattern d'autorisation) ne sont pas dans le repo ; `docs/reports/phase2/` fait foi.

## Next Phase

**Phase 3 — Workflow Builder** (après validation explicite de la phase 2, master §37). Points d'entrée prêts : pattern d'autorisation team-scoped documenté (`.knowledge/decisions/authorization.md` — gabarit `WorkflowPolicy`, extension `TeamPermission`), convention de scoping et de résolution de contexte (`{current_team}` + `EnsureTeamMembership`), socle auth complet (registration, vérification email, 2FA, passkeys).
