# Phase 11 — Security & Hardening

## Summary

Phase sans aucune fonctionnalité nouvelle : **audit de sécurité systématique** puis correctifs TDD. L'audit (rapport : [audit.md](audit.md), réalisé en lecture seule par `architect-logiciel`) a couvert authorization, validation/mass assignment, injection SQL, secrets, SSRF, XSS, CSRF/sessions, rate limiting, uploads, dépendances — et produit **15 constats tracés S1…S15**. Tous les correctifs identifiés ont été appliqués par priorité, chaque faille prouvée par un test rouge d'abord : **9 constats corrigés** (S1 code d'invitation, S2 switch via Policy, S3 schéma catalogue à la sauvegarde, S4 exécutabilité à l'activation, S5 whitelist credentials, **S7 SSRF — épinglage DNS anti-rebinding**, S9/S10 rate limiting runs + budget IA, S12 headers, S13 rel noopener) et **6 acceptés avec justification** (S6 wildcards LIKE, S8 ports, S11 plafond IP webhook, S14 locks, S15 config prod, v-html QR 2FA). Tout le reste audité est conforme (matrice d'autorisation complète, scoping équipe systématique, aucun secret dans props/logs/erreurs/notifications, CSRF exempté consciemment pour `webhooks/*` uniquement, aucun upload). Suites finales : **Pest 825/825 (3 225 assertions, +55 tests)**, **Vitest 170/170**, PHPStan 0 erreur, vue-tsc 0 erreur, Pint propre, `npm run build` OK, `composer audit` + `npm audit` : **0 advisory**.

## Implementation

### Files Created

Backend (1 + 8 fichiers de tests) :

- `app/Http/Middleware/SetSecurityHeaders.php` — X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin (constantes de classe), en append du groupe web (S12)
- `tests/Unit/Integration/HttpClientPinningTest.php` — 15 tests SSRF : épinglage `CURLOPT_RESOLVE` par hop, multi-A, rebinding modélisé, formes obnubilées (dataset) (S7)
- `tests/Feature/Security/` — 36 tests : `NodeConfigValidationTest` (11, S3), `WorkflowActivationValidationTest` (6, S4), `RateLimitingTest` (6, S9+S10), `SecurityHeadersTest` (2, S12), `TeamAccessHardeningTest` (4, S1+S2), `IntegrationCredentialsWhitelistTest` (3, S5)
- `docs/reports/phase11/audit.md` — rapport d'audit initial puis statuts finaux figés par constat

Frontend : aucun nouveau fichier.

### Files Modified

Backend (14) :

- `app/Services/Integration/HttpClient.php` — S7 : résolution A complète (`dns_get_record` DNS_A + DNS_AAAA), `pinEntries()` (host:port:ip, IPv6 entre crochets), pin recalculé à chaque hop de redirect via l'option `curl` de Guzzle ; signature `send()` et raisons d'exception inchangées
- `app/Http/Requests/Workflows/SaveWorkflowGraphRequest.php` — S3 : validation des **valeurs fournies** contre le schéma du catalogue (select ∈ options, range ∈ [min, max]) + branchement `EmailHandler::validate` à la sauvegarde (symétrique du cron) ; vide/null = absent (autosave jamais bloquée)
- `app/Services/Workflow/Handlers/Action/EmailHandler.php` — S3 : `validate()` complété — `to` requis-si-présent (e-mail valide ≤ 255 ; placeholders `{{…}}` tolérés, revalidés après interpolation à l'exécution)
- `app/Http/Requests/Workflows/UpdateWorkflowRequest.php` — S4 : à `status=active` sur un workflow non actif, exécution complète de `WorkflowGraphMapper` + `WorkflowValidator` sur le graphe persisté → erreurs sur le bag 422 (clé `status`)
- `app/Providers/AppServiceProvider.php` — S9 : limiter nommé `workflow-run` (clé `user|team` normalisée, limite en config)
- `routes/web.php` — S9 : `throttle:workflow-run` sur `workflows.run` + `workflows.test-run`
- `app/Actions/Workflows/StartWorkflowRun.php` — S10 : `enforceAiBudget()` avant toute création d'exécution — coût = nombre de nodes `ai.*` (count SQL), refus `ValidationException` si dépassement
- `app/Actions/Workflows/DispatchScheduledWorkflows.php` — S10 : `catch (ValidationException)` → skip silencieux du workflow refusé, dispatch des autres poursuivi
- `bootstrap/app.php` — S12 : `SetSecurityHeaders` sur le groupe web
- `config/workflows.php` — section `rate_limits` (`workflow_run_per_minute` 10, `ai_calls_per_minute` 30), commentaires du fichier
- `.env.example` — bloc « Workflow engine — limites de débit » documentant les 2 variables (FR)
- `app/Http/Controllers/Teams/TeamController.php` — S1 : `code` d'invitation sérialisé seulement si gate `cancelInvitation` (+ `id` toujours) ; S2 : `switch` via `Gate::authorize('view', $team)`
- `app/Http/Controllers/DashboardController.php` — S1 : `pendingInvitations` ajoute `id` ; `code` **conservé** (invitations reçues : le destinataire est le porteur légitime — cf. décisions)
- `app/Http/Requests/Integrations/IntegrationRequest.php` — S5 : `credentials()` filtré par listes blanches typées par intégration (`Arr::only`, clés inconnues retirées silencieusement)

Frontend (7) :

- `resources/js/pages/Welcome.vue` — S13 : `rel="noopener noreferrer"` sur les 3 liens externes
- `resources/js/types/teams.ts` — S1 : `code?: string` sur `TeamInvitation` / `DashboardInvitation` (contexte d'invitation login/register inchangé)
- `resources/js/pages/teams/Edit.vue` — S1 : `:key="invitation.email"` (clé stable non secrète)
- `resources/js/components/CancelInvitationModal.vue` — S1 : garde `code` absent
- `resources/js/components/PendingInvitationsModal.vue` — S1 : clé et processing par slug d'équipe (jamais le code), boutons désactivés sans code (défense)
- `resources/js/components/templates/TemplateGraphPreview.vue` — commentaire trompeur « v-html » corrigé (opportunité de l'audit ; aucun code)
- `.knowledge/memory/maps/map-front-files.json` — registre local mis à jour (gitigné)

Tests modifiés (3) :

- `tests/Feature/Workflows/WorkflowGraphTest.php` — fixture `ai.prompt` `model: 'claude-haiku'` → `'fake/demo'` (1 ligne : valeur hors options du catalogue que S3 refuse désormais — le cas visé par l'audit)
- `tests/Feature/DashboardTest.php` — assertion `pendingInvitations.0.code` repinée avec `id`
- `tests/Unit/Workflow/Handlers/EmailHandlerTest.php` — +4 tests unitaires de `validate()`

### Database Changes

- **Aucune migration** (conforme à l'attendu de la spec : « aucune migration attendue sauf constat » — aucun constat l'exigeant).
- Aucune entité ni état nouveau : factories et seeders inchangés ; `DatabaseSeederTest` reste vert dans la suite (`migrate:fresh --seed` produit toujours une base de démo exploitable).

### Routes

Aucune route nouvelle, renommée ou supprimée. Deux ajouts de middleware sur routes existantes :

| URI                                   | Ajout                                                                            |
| ------------------------------------- | -------------------------------------------------------------------------------- |
| `workflows.run`, `workflows.test-run` | `throttle:workflow-run` (429 au-delà de la limite par user+équipe)               |
| groupe web                            | `SetSecurityHeaders` (append — toutes les réponses web, webhooks publics inclus) |

`wayfinder:generate` non requis (aucun nom/URI/signature de contrôleur changé).

### Frontend Changes

- S13 : 6/6 liens `target="_blank"` de `resources/js` portent désormais `rel="noopener noreferrer"` (grep final propre).
- Adaptation S1 : `code` d'invitation optionnel côté TS ; clés de liste stables sans secret (email sur `teams/Edit`, slug d'équipe dans la modale dashboard) ; identifiant de traitement (`processingTeam`) par slug ; boutons d'annulation déjà gated par `permissions.canCancelInvitation`, accept/refuse fonctionnels pour le destinataire (code toujours présent dans la payload dashboard).
- Zéro changement visuel ; commentaire `TemplateGraphPreview` clarifié (rendu échappé, jamais interprété).

### Tests

TDD sur chaque correctif : le test qui prouve la faille en rouge d'abord, puis le correctif (rouges constatés : 204 au lieu de 422 sur configs invalides, 200/302 au lieu de 429/422 sur les limites, headers absents, `id`/`code` mal sérialisés, arrays en base avec clés arbitraires, options `curl` absentes du transport).

- **55 tests Pest nouveaux** : `tests/Feature/Security/` 36 (matrice authorization S1/S2, whitelist credentials S5, validation catalogue S3, activation S4, rate limiting S9/S10 avec scénarios webhook + scheduler, headers S12), `HttpClientPinningTest` 15 (S7), `EmailHandlerTest` +4 (S3).
- **Suites finales** : Pest **825/825 — 3 225 assertions** (base 770) ; Vitest **170/170** ; PHPStan 0 erreur ; vue-tsc 0 erreur ; Pint propre ; `npm run build` OK.
- Vérifications de périmètre : `grep -rn "v-html" resources/js/` → 1 occurrence acceptée (QR 2FA Fortify) + 1 commentaire ; `target="_blank"` sans rel → vide ; `composer audit` + `npm audit` → 0 advisory.

### Commands Executed

`search-docs` (client HTTP/Guzzle options, rate limiting, FormRequest `after()` + DI, `AssertableInertia::missing`) ; `database-schema` (`team_invitations`) ; `composer show --direct` ; générateurs `--no-interaction` (`make:middleware`, `make:test --pest --unit`) ; `php artisan route:list` (vérification throttle) ; suites ciblées `vendor/bin/pest` par lot puis `php artisan test --compact` (825/825) ; `vendor/bin/pint --dirty --format agent` après chaque lot ; `composer types:check` ; `npm run types:check` / `test:unit` / `build` ; `composer audit` ; `npm audit` ; greps XSS/links/uploads/SQL.

## Key Information

### Technical Decisions

- **A1 — S3 : strict à la sauvegarde vs autosave** : l'audit proposait deux options. Retenu : à la sauvegarde, valider les **valeurs fournies** contre le schéma du catalogue (select/range) + `EmailHandler::validate` « requis-si-présent » — l'autosave du builder (PUT complet ~800 ms, textes vides par défaut) n'est jamais bloquée par un champ requis vide, conformément à l'intention documentée phase 3 (« tolerant on purpose »). L'exécutabilité complète (champs requis inclus) est exigée **à l'activation** (S4). Le cron `trigger.schedule` reste strict à la sauvegarde (comportement phase 7, inchangé).
- **A2 — S7 : épinglage plutôt que double résolution** : `CURLOPT_RESOLVE` rempli depuis UNE seule résolution par hop rend le rebinding structurellement impossible (curl ne refait aucune résolution sur un host épinglé) et couvre le multi-A ambigu ; les formes obnubilées (decimal/hex/octal) passent désormais par la résolution complète et restent refusées (`blocked_host`, seul le `technicalDetail` — logs uniquement — change de libellé).
- **A3 — S10 : budget IA dans `StartWorkflowRun`** (et pas en middleware) : les trois chemins de déclenchement (manuel, webhook, scheduler) passent par cette action. Refus `ValidationException` → 422 propre sur run manuel et webhook (contrat 202/404/413/422 respecté), skip silencieux du scheduler. Idempotence webhook conservée : le refus arrive après l'insertion `webhook_requests`, un retry avec le même `X-Request-Id` répond `duplicate` sans réexécuter.
- **A4 — S1 : périmètre exact du masquage du `code`** : `teams/edit` liste des invitations **émises** vues par des membres tiers → `code` masqué sans `cancelInvitation`. Le dashboard liste des invitations **reçues** par l'utilisateur courant → `code` conservé : le destinataire est le porteur légitime (il l'a reçu par e-mail ; `ValidTeamInvitation` exige la correspondance d'e-mail ; les props ne sortent pas vers des tiers). Le premier livrable avait étendu le masquage au dashboard — corrigé en cours de phase (test repiné), le flux accept/refuse du destinataire est préservé.
- **A5 — S2** : `TeamPolicy::view` (= appartenance) utilisé tel quel pour `switch`, plutôt qu'une méthode Policy dédiée — sémantique identique, gabarit authorization.md respecté.
- **A6 — Acceptations** : S6 (wildcards LIKE : aucun franchissement d'équipe), S8 (ports : cibles internes inatteignables), S11 (webhook sans plafond IP : token 288 bits), S14 (locks gitignorés : décision d'équipe), S15 (config prod), v-html QR 2FA (SVG Fortify pour l'utilisateur courant) — justifications détaillées dans `audit.md`.
- **A7 — Aucune dépendance ajoutée** (master §23/§34) : headers via middleware maison, rate limiting via les limiteurs natifs Laravel, SSRF via les options curl existantes de Guzzle.

### Gotchas & Solutions

- **`RateLimiter::tooManyAttempts($key, 0)` ne refuse jamais** : sans timer de fenêtre ouvert, le premier appel retourne false et réinitialise. Solution : forme `attempts($key) + coût > budget` puis `RateLimiter::increment($key, 60, $coût)` (seule API avec `amount` en 13.32) — gère aussi le budget 0.
- **Param de route dans un limiter** : `current_team` est déjà le modèle `Team` quand le limiter tourne (`SubstituteBindings` précède les middlewares de route), mais la clé `workflow-run` normalise les deux cas (modèle → id, sinon brut) — typage strict exigé par PHPStan.
- **Comptage des nodes `ai.*`** : compter en base (`where type like 'ai.%'`) et non sur la collection `$workflow->nodes` du scheduler — celle-ci est pré-filtrée sur `trigger.schedule` (piège constaté au Green).
- **`catch (ValidationException)` avant `catch (Throwable)`** dans `DispatchScheduledWorkflows` — même piège que le listener de la phase 10 (un catch `Throwable` non qualifié/antérieur avale l'exception ciblée).
- **Parallélisation des lots** : 5 correctifs développés en parallèle sur ensembles de fichiers **disjoints** (tests `:memory:` + cache array) — zéro collision de base ; seuls résidus : un test d'un lot vu rouge par un autre avant correction (ordre d'écriture), et `pint --dirty` ayant retouché 2 fichiers d'autres lots (style pur).
- **`dns_get_record` n'existe pas sous Windows** (poste dev, pré-existant avec les AAAA) : aucun test n'en dépend (closure `resolveHost` injectée) ; production Linux.
- **`CURLOPT_RESOLVE`** : format `HOST:PORT:ADDRESS`, IPv6 entre crochets (curl ≥ 7.57) ; l'option `curl` passe intacte à travers le client HTTP Laravel (vérifié dans le vendor) et `Http::fake()` expose les options au callback de test.

### Commands & Config

- Nouvelles variables (documentées dans `.env.example`, défauts en `config/workflows.php` § `rate_limits`) :
    - `WORKFLOW_RUN_RATE_LIMIT_PER_MINUTE=10` — plafond par utilisateur+équipe/min sur `workflows.run` + `workflows.test-run` (429).
    - `WORKFLOW_AI_RATE_LIMIT_PER_MINUTE=30` — budget d'appels IA par équipe/min (fenêtre glissante 60 s, coût = nombre de nodes `ai.*` par run).
- Valeurs de production à poser au déploiement (S15, aucun code à changer) : `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` aligné sur le domaine réel.
- `php artisan migrate` non requis (aucune migration) ; `wayfinder:generate` non requis.

### Version Notes

- **Laravel 13.32** : `RateLimiter::increment()` est la seule API de hit avec `amount` ; `SubstituteBindings` précède les middlewares de route (paramètres déjà bindés dans les limiteurs) ; `FormRequest::after()` bénéficie de l'injection du conteneur.
- **Guzzle 7.15** : `options['curl']` fusionné dans la config du handle ; `CURLOPT_RESOLVE` figure dans la allow-list `CurlFactory::supportedCurlOptions` (aucune dépréciation Guzzle 8 à prévoir).
- **Testing** : `Http::fake()` transmet `($request, $options)` au callback — les options curl du transport sont vérifiables sans réseau.

## Future Ideas (Not Planned)

- **Signature HMAC entrante des webhooks** — au-delà du token d'URL, garantir l'origine de l'appel ; à évaluer à l'ouverture de Faucon à des intégrations tierces.
- **CSP stricte** — les inlines Vite/Inertia la rendent non triviale ; envisager `nonce` + rapport-only d'abord.
- **2FA exigée pour les actions sensibles** (régénération de token webhook, suppression d'équipe) — Fortify `confirm` déjà en place pour la 2FA elle-même.
- **IP allowlist par webhook + plafond webhook par IP** — réévaluer si du volume non sollicité apparaît sur les 404 (S11).
- **Allowlist de ports HTTP sortants** (`WORKFLOW_HTTP_ALLOWED_PORTS`) — si le port-scanning de cibles publiques devient un souci (S8).
- **Rotation des credentials / audit trail d'administration** — veille du prompt de phase ; utile à l'industrialisation.
- **Versionner `composer.lock` / `package-lock.json`** (S14) — décision d'équipe à porter hors périmètre code.
- **Échapper les wildcards LIKE de la recherche d'exécutions** (`addcslashes % _`) — cosmétique, si retouche UX de la recherche un jour (S6).

## Known Limitations

- **SMTP probe** : `ConnectionTester::testSmtp` sonde `host:port` fournis par les credentials de l'équipe — un membre peut faire sonder un host interne par le serveur (réponse ok/échec) ; assumé (l'utilisateur contrôle ses credentials), consigné.
- **Budget IA compté au dispatch** : les retries internes du job (`max_tries=2`) et le test-run synchrone ne re-consomment pas le budget ; le test-run reste borné par le limiter `workflow-run` (10/min).
- **Webhook sans plafond par IP** (S11) : les 404 sur tokens inventés ne sont pas bornés.
- **Ports HTTP sortants non restreints** (S8) : cibles internes inatteignables, mais port-scan de cibles publiques possible (plafonné).
- **Sauvegarde tolérante** : un graphe incomplet reste sauvegardable (autosave) — seul l'activateur bloque (S4). C'est l'arbitrage A1.
- **Locks gitignorés** (S14) : builds non verrouillés en CI/prod — décision d'équipe en attente.
- Parcours manuel `migrate:fresh --seed` + scénario UI non exécuté sur la base dev (destructif) — aucun changement de seeder, comportement prouvé par `DatabaseSeederTest` (vert).

## Next Phase

Phase 12 — **Testing** (prompt : `.knowledge/prompts/phase12.md`).
