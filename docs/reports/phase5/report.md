# Phase 5 — Actions & Integrations

## Summary

La phase 5 connecte Faucon au monde extérieur. Côté backend : entité `Integration` (credentials chiffrés au repos via cast `encrypted:array`, Policy team-scoped, permissions `integration:create|update|delete`, compteur d'usage, test de connexion par type), garde SSRF `App\Services\Integration\HttpClient` (blocage IPv4/IPv6 complet, redirections suivies **manuellement** avec re-validation par hop, DNS par closure injectable, plafond de réponse), et **trois nouveaux handlers** enregistrés au registre sans toucher au moteur : `action.http` (interpolation `{{ }}`, headers « Clé: valeur », `failure_policy` fail/continue, auth par intégration bearer/basic/header), `action.email` (Mailable texte, boundary `EmailSender` : chemin mailer Laravel par défaut, chemin SMTP Symfony par intégration), `trigger.webhook` (endpoint public `POST /webhooks/{token}`, idempotence `X-Request-Id` par table `webhook_requests` + unique composite, throttle nommé 60/min/token, payload 64 Kio / profondeur 10, purge quotidienne). Arbitrage utilisateur : le node HTTP est **`action.http`** (catégorie Action) conformément au prompt de phase — `data.http_request` est retiré du catalogue (catalogue inchangé à 15 types, répartition 3/3/2/3/4) et les maquettes corrigées d'abord (design-first). Côté front : page settings **Intégrations** fidèle à la maquette enrichie (statut du dernier test, méta non secrète, secret jamais renvoyé, dialog à champs conditionnels par type, confirmation de suppression), inspecteurs builder complets (`action.http` 6 champs, section webhook dédiée avec URL copiable + régénération confirmée + hint idempotence, champ Intégration), URL webhook **jamais dans les props** (endpoint JSON dédié, régénération gated `workflow:update`). TDD Pest et Vitest sur chaque lot. Suites : **452 tests Pest, 1500 assertions, 0 échec** (baseline 322) + **83 tests Vitest** (baseline 36). PHPStan niveau 7 : 0 erreur. Build Vite OK. Aucune nouvelle dépendance. Absorptions des rapports précédents : le test-run devient possible sur les workflows à trigger webhook (limitation phase 4 levée) et la confirmation avant suppression (Future Ideas phase 3) est appliquée aux intégrations.

## Implementation

### Files Created

Backend :

- `app/Models/Integration.php` — cast `credentials` → `encrypted:array`, `IntegrationType` enum, fillable minimal.
- `app/Models/WebhookEndpoint.php` — `token` cast `encrypted`, `token_hash`, `hashToken()`, `url()` ; relation `Workflow::webhookEndpoint()`.
- `app/Models/WebhookRequest.php` — journal d'idempotence, `MassPrunable` (fenêtre 24 h), sans timestamps.
- `database/migrations/2026_09_19_201203_create_integrations_table.php` — `credentials` **TEXT** (longueur imprévisible du chiffrement), FK `team_id` cascade après `id`, `last_tested_at`/`last_test_succeeded`, index `(team_id, type)`.
- `database/migrations/2026_09_19_213441_create_webhook_tables.php` — `webhook_endpoints` (`workflow_id` FK cascade **unique**, `token` 64 chiffré, `token_hash` 64 unique) ; `webhook_requests` (`token_hash`, `request_id_hash`, `received_at`, **unique composite**, index `received_at`).
- `database/factories/{IntegrationFactory,WebhookEndpointFactory}.php` — états `genericHttp()`, `smtp()`, `withCredentials()`, `testSucceeded()`, `testFailed()`, `withToken()`.
- `app/Enums/IntegrationType.php` — `GenericHttp = 'generic_http'`, `Smtp = 'smtp'`, libellés FR ; extensible (providers IA en phase 6).
- `app/Policies/IntegrationPolicy.php` — viewAny/view = membership ; create/update/delete/test = permissions (test = update).
- `app/Services/Integration/HttpClient.php` — wrapper unique de la facade `Http` : schéma http(s), classification IPv4+IPv6 (loopback, privé, link-local, ULA, CGNAT, multicast, réservé, mappé ::ffff:/96 déplié), résolution A+AAAA par **closure injectable** (zéro DNS en test), **redirections suivies manuellement (≤ 2) avec re-validation du garde par hop**, timeouts courts, plafond de réponse 1 Mio.
- `app/Services/Integration/Exception/HttpClientException.php` — `reason` machine (`invalid_url|blocked_host|network_error|timeout|response_too_large`) + message FR + `technicalDetail()` logs (miroir `NodeExecutionException`).
- `app/Services/Integration/IntegrationResolver.php` — `find(?string $id): ?Integration`, closure `$finder` injectable pour les tests.
- `app/Services/Integration/SmtpTransportFactory.php` — `dsnFor()` pur (rawurlencode des userinfo, encryption tls/ssl/none), `mailerFor()` (Symfony Mailer — moteur déjà dépendu par Laravel), `probeFor()` (handshake `SmtpTransport::start()`) ; PHPDoc de classe documentant le lien avec le Mailer livré par Laravel (facade `Mail`/`MailManager`, credentials dynamiques vs `config/mail.php`, `Mail::fake()` inopérant sur un transport construit à la main).
- `app/Services/Integration/EmailSender.php` — boundary d'envoi : `$smtp === null` → `Mail::to()->send()` (intercepté par `Mail::fake()`) ; sinon Symfony `Mime\Email` via `SmtpTransportFactory`, from = `credentials.from` sinon défaut applicatif ; closure `$smtpSender` injectable pour les tests.
- `app/Services/Integration/ConnectionTester.php` — test de connexion par type : generic_http = GET `baseUrl` via le garde SSRF (2xx ok, 401/403 échec d'auth explicite) ; smtp = `probeFor()` ; met à jour `last_tested_at`/`last_test_succeeded` ; message FR sans credential.
- `app/Actions/Integrations/{CreateIntegration,UpdateIntegration}.php` ; `app/Actions/Workflows/EnsureWebhookEndpoint.php` (create-if-missing / delete-if-absent, appelé en fin de `SaveWorkflowGraph` et par `WebhookUrlController`).
- `app/Http/Requests/Integrations/{IntegrationRequest (base),StoreIntegrationRequest,UpdateIntegrationRequest}.php` — validation conditionnelle par type en `after(): array`, erreurs `credentials.<champ>`, messages FR (’), `type` requis aussi à l'update, credentials optionnels à l'update (absents = inchangés).
- `app/Http/Controllers/Integrations/{IntegrationController,TestIntegrationConnectionController}.php` — props `IntegrationSummary` (`id, name, type, lastTestedAt, lastTestSucceeded, usedByWorkflows, meta`) **sans aucun credential** ; compteur d'usage en PHP sur `workflow_nodes` (portable MariaDB/sqlite).
- `app/Http/Controllers/Workflows/{WebhookController,WebhookUrlController,RegenerateWebhookTokenController}.php` — endpoint public (lookup par hash, éligibilité active + node webhook, 413/422 bornes, idempotence `insertOrIgnore`, run synchrone via `TestRunWorkflow` → 200 `ExecutionResult`) ; URL (`{url}`, firstOrCreate) ; régénération (token+hash en transaction).
- `app/Services/Workflow/Handlers/Action/HttpHandler.php` — `{method, url, headers, body, integration_id?, failure_policy}` ; interpolation stricte ; headers « Clé: valeur » par ligne (≤ 20, clé ≤ 128, valeur ≤ 2000) ; auth intégration injectée APRÈS les headers du node (credentials gagnent) ; output `{status, body}` (JSON décodé sinon texte) ; `fail`/`continue`, **violation SSRF = échec toujours** ; raisons `integration_not_found`/`invalid_integration_type`/`integration_invalid`.
- `app/Services/Workflow/Handlers/Action/EmailHandler.php` — to unique validé (jamais affiché en erreur), subject/body interpolés, output `{sent, to}`, échec d'envoi `email_send_failed` (détail transport en logs).
- `app/Services/Workflow/Handlers/Trigger/WebhookHandler.php` — passthrough de l'input (payload webhook ou échantillon du test-run) ; `validate()` tolère les clés legacy `method`/`path`.
- `app/Mail/WorkflowActionEmail.php` + `resources/views/mail/workflow-action.blade.php` (texte pur, `{!! $body !!}` commenté).
- `app/Support/IntegrationSummaries.php` — mapping « non secret » unique partagé par les deux pages (`forTeam`, `metaFor` : baseUrl ou host:port — champs d'auth jamais consultés, troncature 120).
- `config/workflows.php` — bornes HTTP + webhook tunables (défauts en dur, lisibles via `env()`).

Frontend :

- `resources/js/types/integrations.ts` — `IntegrationType`, `IntegrationSummary` (+ `meta`) ; barrel `types/index.ts`.
- `resources/js/lib/integrationTypes.ts` — schéma de formulaire par type (miroir documenté de la validation backend), limites, présentation (avatar lettre, libellés « HTTP générique » / « SMTP / E-mail »), `validateIntegrationForm`, `integrationMetaLabel` (méta réelle, repli générique).
- `resources/js/composables/useIntegrationConnectionTest.ts` — machine idle → testing → résultat, un seul test à la vol, couche HTTP injectée.
- `resources/js/composables/useClipboardCopy.ts` — `navigator.clipboard` → repli `execCommand`, état `copied` temporisé 2 s.
- `resources/js/lib/nodeOptionLabels.ts` — fail → « Échouer », continue → « Continuer », repli valeur brute.
- `resources/js/components/integrations/{IntegrationCard,IntegrationFormDialog,DeleteIntegrationDialog}.vue` — carte maquette (badge statut Connectée/Échec/Non testée, méta mono + « Secret masqué », Utilisé par N workflows, Tester avec état en vol, menu Modifier/Supprimer) ; dialog création/édition (champs conditionnels par type, secrets en password + œil, « Inchangé si vide » en édition, validation live + 422 champ par champ) ; dialog suppression avec avertissement d'usage.
- `resources/js/pages/settings/Integrations.vue` — grille responsive, carte pointillée « Ajouter une intégration » (gating), empty state.
- `resources/js/components/builder/WebhookInspectorSection.vue` — GET `workflows.webhook.url` au montage (loading/error/vide), URL mono + copier (« Copié » 2 s), « Régénérer le token » (dialog de confirmation irréversible, gated `canUpdateWorkflow`), hint `X-Request-Id` (`v-pre`) ; l'URL ne vit que dans l'état local du composant.

Tests (créés d'abord, Red → Green par lot) :

- `tests/Feature/Integrations/{IntegrationCrudTest,IntegrationPolicyTest,IntegrationConnectionTest}.php` — CRUD complet, chiffrement prouvé (valeur brute DB ≠ plaintext, relecture décryptée), props sans credentials, compteur d'usage, scoping 404, matrice de rôles, test de connexion fake/stub.
- `tests/Unit/Integration/{IntegrationResolverTest,HttpClientTest,SmtpTransportFactoryTest}.php` — datasets SSRF complets sans aucun appel réseau (`Http::preventStrayRequests()`, DNS par closures, plages TEST-NET), redirections par hop, plafond, DSN pur.
- `tests/Unit/Workflow/Handlers/{HttpHandlerTest,EmailHandlerTest,WebhookHandlerTest}.php` — `Http::fake`/`Mail::fake`, policy fail/continue, interpolation, auth jamais dans l'output, passthrough webhook.
- `tests/Unit/Mail/WorkflowActionEmailTest.php`.
- `tests/Feature/Workflows/{WebhookEndpointTest,WebhookTriggerTest,WorkflowEditPropsTest,WorkflowEngineExtensibilityTest}.php` — cycle de vie de l'endpoint, matrice webhook (200+exécution, 404 indiscernables, 413, 422, **doublon → exécution unique**, pas d'idempotence sans header, 429, purge), props Edit sans secret, **preuve d'extension webhook → http → email sans modification du moteur**.
- Vitest : `lib/__tests__/integrationTypes.spec.ts` (24), `composables/__tests__/{useIntegrationConnectionTest,useClipboardCopy}.spec.ts` (6 + 8), `lib/__tests__/nodeOptionLabels.spec.ts` (2).

### Files Modified

- `app/Enums/TeamPermission.php` (+`integration:create|update|delete`), `app/Enums/TeamRole.php` (Admin += les 3, Member aucune), `app/Data/TeamPermissions.php` (+3 booléens), `app/Concerns/HasTeams.php`, `app/Models/Team.php` (`integrations()`).
- `app/Services/Workflow/NodeCatalog.php` — **swap `data.http_request` → `action.http`** (6 champs, type `integration`), `trigger.webhook` → `fields: []`, `action.email` += `integration_id`, libellés harmonisés maquette ; `app/Data/Workflow/NodeDefinition.php` (union des types de champ += `integration`).
- `app/Providers/AppServiceProvider.php` — registre += 3 handlers (`$this->app->make()`), rate limiter nommé `webhooks` (clé = sha256 du token, jamais le token brut).
- `app/Actions/Workflows/SaveWorkflowGraph.php` (+ `EnsureWebhookEndpoint` après transaction), `TestRunWorkflow.php` (PHPDoc « réutilisée par l'endpoint webhook »).
- `app/Http/Requests/Workflows/SaveWorkflowGraphRequest.php` — `validateIntegrationReferences()` (cross-team 422, dangling tolérée) + tolérance `null` (découverte `ConvertEmptyStringsToNull`).
- `app/Http/Controllers/Workflows/WorkflowController.php` — page Edit += prop `integrations` (summary+meta, sans credentials).
- `app/Models/Workflow.php` (+`webhookEndpoint()`), `bootstrap/app.php` (`preventRequestForgery(except: ['webhooks/*'])`), `routes/web.php` (5 routes integrations + 2 routes builder webhook + route publique), `routes/console.php` (`model:prune` daily), `.env.example` (8 clés commentées).
- Tests ajustés : `NodeCatalogTest` (répartition 3/3/2/3/4, définitions), `WorkflowGraphTest` (graphe webhook sans config legacy), `WorkflowGraphValidationTest` (références d'intégration), `IntegrationCrudTest` (retrait du bypass Inertia, meta).
- Front : `NodeInspector.vue` (champ `integration` + section webhook + `nodeOptionLabel`), `Edit.vue` (props `integrations`, `workflowId`), `layouts/settings/Layout.vue` (item « Intégrations »), `types/{workflows,teams,index}.ts`, `vitest.config.ts`, `tsconfig.json`, `useWorkflowBuilder.spec.ts` (fixture catalogue).
- Maquettes (design-first, validées utilisateur) : `.knowledge/design/{builder.html,js/builder.js,settings.html}` — node HTTP déplacé en Actions avec 6 champs, section webhook inspecteur + dialog régénération, champ Intégration sur email, section Intégrations refondue (statut de test, secret jamais révélé, champs par type, suppression confirmée).

### Database Changes

- `integrations` : `team_id` FK cascade, `type` (32), `name`, `credentials` **TEXT chiffré** (cast `encrypted:array`), `last_tested_at`, `last_test_succeeded`, timestamps, index `(team_id, type)` — hard delete (effacement garanti des secrets).
- `webhook_endpoints` : `workflow_id` FK cascade **unique**, `token` (64, chiffré), `token_hash` (64, unique), timestamps.
- `webhook_requests` : `token_hash`, `request_id_hash`, `received_at`, **unique `(token_hash, request_id_hash)`**, index `received_at`, pas de timestamps, purge quotidienne (> 24 h) via `model:prune`.
- Dev : les deux migrations appliquées sur MariaDB (`migrate`, puis `migrate:fresh` final).

### Routes

| Méthode | URI | Nom | Protection |
| --- | --- | --- | --- |
| GET | `{current_team}/settings/integrations` | `integrations.index` | auth + membership |
| POST | `{current_team}/settings/integrations` | `integrations.store` | Policy `create` |
| PATCH | `{current_team}/settings/integrations/{integration}` | `integrations.update` | Policy `update` |
| DELETE | `{current_team}/settings/integrations/{integration}` | `integrations.destroy` | Policy `delete` |
| POST | `{current_team}/settings/integrations/{integration}/test` | `integrations.test` | Policy `test` (= update) |
| GET | `{current_team}/workflows/{workflow}/webhook-url` | `workflows.webhook.url` | Policy `view` |
| POST | `{current_team}/workflows/{workflow}/webhook-regenerate` | `workflows.webhook.regenerate` | Policy `update` |
| POST | `/webhooks/{token}` | `webhooks.handle` | **public** — `throttle:webhooks`, CSRF except `webhooks/*` |

`php artisan wayfinder:generate --with-form` exécuté après chaque évolution de routes ; le front n'a aucune URL en dur.

### Frontend Changes

- **Page Intégrations** (settings) : parcours complet créer → tester → modifier → supprimer, fidèle à la maquette enrichie ; credentials jamais renvoyés (aucun reveal/copy — écart assumé voulu par le prompt de phase), secret masqué par badge ; toasts vue-sonner ; gating complet par permissions.
- **Builder** : inspecteur `action.http` complet (6 champs dont en-têtes mono et sélecteur d'intégration alimenté par la prop d'équipe), champ « Intégration SMTP (optionnelle) » sur `action.email`, section webhook dédiée (URL publique copiable, régénération confirmée — l'ancienne URL meurt, hint idempotence) ; URL webhook **jamais dans les props de page** (endpoint JSON dédié + état local) ; `NodeFieldType` += `integration` ; libellés FR des options.
- Maquettes mises à jour AVANT l'implémentation (validation utilisateur) — les écrans de la phase dépassaient la maquette d'origine.

### Tests

- **Pest** : 322 → **452 tests, 1500 assertions, 0 échec** (+130). Chiffrement au repos, SSRF (datasets sans réseau), handlers (fake HTTP/Mail), webhook (idempotence, throttle, purge, 404 indiscernables), policies, props sans secrets, extensibilité du moteur.
- **Vitest** : 36 → **83 tests** (+47). Formulaires d'intégration, machine de test de connexion, clipboard, libellés d'options, fixture catalogue.
- PHPStan niveau 7 : 0 erreur. Pint : passed à chaque lot. `npm run types:check` / `check` / `build` : OK. Zéro appel réseau dans les tests (`Http::preventStrayRequests`, DNS stubé).

### Commands Executed

`php artisan make:` (model -f, enum, policy, requests, controllers invokables, actions, class, mail, test --pest) ; `search-docs` (HTTP client, Mail, rate limiting, encrypted casts, preventRequestForgery, Prunable, SmtpTransport) ; `php artisan migrate` / `migrate:fresh` (dev) ; `php artisan wayfinder:generate --with-form` ; `vendor/bin/pint --dirty --format agent` ; `vendor/bin/phpstan analyse --no-progress` ; `php artisan test --compact` ; `npm run test:unit` / `types:check` / `check` / `build`.

## Key Information

### Technical Decisions

- **`action.http` remplace `data.http_request`** (arbitrage utilisateur U1) : le prompt de phase nomme `action.http` ; créer un 2ᵉ node HTTP aurait laissé `data.http_request` en piège `handler_missing` permanent. Le catalogue reste à 15 types (3/3/2/3/4) ; les graphes dev contenant l'ancien type deviennent `unknown_type` (pas de migration de données). Maquettes corrigées d'abord (design-first).
- **Token webhook en table dédiée, jamais en config de node** : la config est sérialisée dans les props Edit et renvoyée telle quelle par l'autosave — un token en config fuiterait et serait écrasé ; le cast `encrypted` n'étant **pas requêtable**, le lookup passe par `token_hash` (SHA-256, unique) et la colonne `token` chiffrée sert à reconstruire l'URL affichable. `webhook_endpoints` (un par workflow, unique) + régénération serveur uniquement.
- **URL webhook hors props (exception délibérée, documentée)** : capability URL destinée à être copiée (même statut que l'URL d'un webhook Stripe/GitHub), servie par un endpoint JSON dédié (`workflows.webhook.url`, Policy `view`), jamais dans une prop, un log ou le graphe ; régénération gated `workflow:update`.
- **Idempotence** : header `X-Request-Id` uniquement (absent = pas de déduplication — déduire du body avalerait des livraisons légitimes identiques) ; `insertOrIgnore` + unique composite ; doublon → 200 `{"status":"duplicate"}` **sans ré-exécution** (pas de replay : seuls des hash sont stockés) ; fenêtre 24 h prunable ; un run qui échoue reste « consommé » (le fournisseur ne retry pas).
- **Garde SSRF** : validation par hop avec **redirections suivies manuellement** (Guzzle non laissé suivre — bypass par redirect sinon) ; chaque IP résolue (A + AAAA) classifiée ; DNS rebinding explicitement hors scope (phase 11) ; violation SSRF = échec du node même en `failure_policy=continue` (erreur de configuration, pas un incident métier).
- **Headers en chaîne « Clé: valeur »** : la whitelist de config contraint les valeurs à des scalaires — la chaîne multi-lignes passe sans toucher au contrat de sauvegarde ; un type de champ `keyvalue` structuré est réévalué si un 2ᵉ cas réel apparaît.
- **Boundary `EmailSender`** : `Mail::fake()` n'intercepte pas un transport Symfony construit à la main — le chemin par défaut passe par le mailer Laravel (`Mail` facade, fakeable), le chemin SMTP (credentials dynamiques par équipe, pas d'entrée `config/mail.php`) est isolé et testé par stub.
- **Référence d'intégration validée À LA SAUVEGARDE, dangling tolérée** : sinon l'autosave du builder se bloquerait en 422 après toute suppression ; à l'exécution, intégration introuvable → échec explicite du node. Suppression d'une intégration utilisée jamais bloquée — la carte affiche l'usage et le dialog de confirmation prévient.
- **Champ `integration` dédié** (auto-descriptif) plutôt qu'un marqueur `options: 'dynamic:…'` ; options portées par une prop d'équipe (`[{id, name, type, …}]`, jamais de credentials).
- **Permissions** : `integration:create|update|delete` = Admin (les secrets se gèrent) ; la liste (noms + ids + meta) reste accessible à tout membre — un membre doit pouvoir référencer une intégration dans un node.
- **Absorptions des rapports précédents** : limitation phase 4 « trigger webhook non exécutable » levée (le test-run fonctionne désormais sur les workflows webhook — payload d'échantillon = simulation du payload) ; Future Ideas phase 3 « confirmation avant suppression » appliquée aux intégrations. Les autres Futures (on-fail, boucles, exécution partielle, parallélisme, capture input par node, duplication de node, `trigger.schedule`) restent non repris, avec phase cible.

### Gotchas & Solutions

- **Cast `encrypted`** : colonne TEXT obligatoire (longueur imprévisible) et **non requêtable en SQL** — toute recherche passe par une colonne hash dédiée (`token_hash`).
- **`Mail::fake()` vs transport Symfony** : tout envoi passe par `EmailSender` ; le chemin smtp se teste par closure stub, pas par fake.
- **`Http::fake()` n'intercepte pas le DNS** : le resolver est une closure injectable — sans elle, les tests feraient du DNS réel ; en test les closures throw pour prouver qu'aucune résolution n'a lieu sur les littéraux IP.
- **`Http::failedConnection()`** (pas `failedRequest()`) est l'API Laravel 13 pour simuler une erreur transport.
- **CSRF** : méthode Laravel 13 = `$middleware->preventRequestForgery(except: ['webhooks/*'])` dans `bootstrap/app.php`.
- **`ConvertEmptyStringsToNull`** : une config `integration_id: ''` arrivait en `null` et la whitelist de sauvegarde la rejetait — règle `nodes.*.config.*` tolérante à `null` (les handlers traitent `null` comme absent).
- **`$table->foreignId(...)->constrained()->cascadeOnDelete()->unique()`** : PHPStan refuse le chaînage `unique()` sur `ForeignKeyDefinition` — `$table->unique('workflow_id')` séparé.
- **`toThrow(fn (Exception $e) => …)`** masque le type réel de l'exception (un import manquant restait opaque) — préférer un try/catch de debug ponctuel.
- **`make:exception`** génère dans `app/Exceptions/` (hors convention) — classe créée via `make:class` à l'emplacement cible.
- **`json_decode` `$depth`** : `max(1, (int) config(...))` pour satisfaire `int<1, max>` (PHPStan).
- **Symfony Mailer brut** attend un `Symfony\Component\Mime\Email`, pas un Mailable Laravel.
- **reka-ui (Select)** refuse les valeurs vides — sentinelle `__no_integration__` convertie en `''` côté config (traitée comme absent par le backend).
- **`wayfinder:generate --with-form`** (règle projet, confirmée encore) — exports imbriqués `routes/workflows/webhook` vérifiés.

### Commands & Config

- `config/workflows.php` (nouveau) : `http.timeout` 10 s, `http.connect_timeout` 5 s, `http.max_redirects` 2, `http.max_response_bytes` 1 048 576 ; `webhook.max_payload_bytes` 65 536, `webhook.max_json_depth` 10, `webhook.rate_limit_per_minute` 60, `webhook.idempotence_window_days` 1.
- `.env.example` : `WORKFLOW_HTTP_*` / `WORKFLOW_WEBHOOK_*` (commentées, défauts documentés). Aucun secret dans le repo.
- Test webhook manuel : `curl -X POST https://<app>/webhooks/<token> -H "Content-Type: application/json" -H "X-Request-Id: <id>" -d '{"email":"…"}'` — l'URL est copiable depuis l'inspecteur du builder.
- Purge : `php artisan model:prune` (programmée daily).

### Version Notes

- Laravel 13 : `preventRequestForgery()` (nom 13.x), `Http::failedConnection()`, rate limiter nommé + `Limit::perMinute()->by()`, `MassPrunable` + `model:prune`, cast `encrypted:array` (TEXT, non requêtable).
- symfony/mailer v8.1.7 (dépendance Laravel existante) : `SmtpTransport::start()` pour le probe, DSN `smtp://user:pass@host:port?encryption=…`, `Mime\Email` pour le Mailer brut.
- Inertia v3 : props Inertia + endpoints JSON dédiés pour les valeurs sensibles (URL webhook) ; tests feature avec assertions `missing('credentials')`.
- Pest 3 : datasets pour les matrices SSRF et policy ; `Carbon::setTestNow` pour la fenêtre de purge.

## Future Ideas (Not Planned)

- **Signature HMAC des webhooks entrants** : vérifier l'expéditeur côté serveur (header signé) — à évaluer en phase 11 (durcissement) avec le rate limiting avancé.
- **OAuth2 avec refresh tokens** pour les intégrations : quand un fournisseur réel l'exigera (phase 6+ pour les providers IA).
- **Intégrations supplémentaires** (Slack, Discord, Telegram, Google Sheets, Notion…) : à chaque fois qu'un node en aura l'usage (même règle que l'arbitrage U2).
- **Replay de la réponse originale d'un webhook doublon** : exigera la persistance des exécutions (phase 7).
- **Rate limiting par service externe / quota par intégration** : phase 11 ou retour d'usage.
- **Streaming strict du plafond de réponse HTTP** (au lieu du cap bufferisé 1 Mio) : phase 7/11.
- **Champ `keyvalue` structuré pour les en-têtes** (contrat front + back) : si un 2ᵉ cas réel apparaît.
- **Rotation de clé dédiée** (au lieu de la re-saisie via Modifier) : passe UX phase 10 si un usage réel apparaît.

## Known Limitations

- **Exécution synchrone dans la requête webhook** (timeout runner 5 s + timeouts HTTP 10 s) — async en phase 7 ; un run échoué répond quand même 200 (résultat, pas erreur HTTP).
- **Garde SSRF « basique »** : pas de protection DNS rebinding (validation avant requête, pas au connect), plafond de réponse bufferisé, pas de pinning d'IP — phase 11.
- **Idempotence** : sans `X-Request-Id`, pas de déduplication ; la réponse originale n'est pas rejouée (hashes seuls) ; fenêtre 24 h (un retry après 24 h ré-exécute).
- **URL webhook consultable par tout membre pouvant voir le builder** (capability, Policy `view`) ; régénération réservée `workflow:update` ; jamais dans props/logs.
- **Fenêtre de collision d'id d'intégration** après suppression + recréation par une autre équipe : la résolution à l'exécution est par id (le contexte moteur ne porte pas l'équipe) — impact faible (les credentials qui couleraient seraient ceux de l'attaquant lui-même), durcissement phase 7/11.
- Les graphes dev contenant `data.http_request` sont invalides (`unknown_type`) — pas de migration de données.
- Types encore sans handler : `trigger.schedule` (phase 7), `ai.*` (phase 6), `logic.filter`, `action.message`, `action.delay` (non planifiés — échec `handler_missing` explicite).
- La ligne méta des cartes d'intégration expose uniquement baseUrl / host:port (non secret) — tronquée à 120 caractères.
- Vérification interactive navigateur (créer une intégration → la référencer dans un node → webhook curl → test-run) à jouer manuellement — validée au niveau contrat (suites vertes, feature tests).

## Next Phase

**Phase 6 — AI Provider & AI Nodes** : abstraction `App\Services\Ai` (interface provider — OpenAI, Anthropic, fake de test), gestion des clés via intégrations (l'enum `IntegrationType` s'étendra aux providers), handlers `ai.classification`, `ai.generation`, `ai.summary` — même contrat d'extension : un handler + un enregistrement au registre, sans toucher au moteur.
