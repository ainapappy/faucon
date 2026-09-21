# Phase 12 — Testing

## Summary

Phase de **qualité de tests**, aucune fonctionnalité nouvelle. L'état des lieux a confirmé une
suite déjà dense et saine : 825 tests verts (~86 s) avant la phase, tous les modules critiques
listés par la spec (Runner, Validator, Interpolator, Redactor, AIProviderManager, HttpClient/SSRF,
RunWorkflowJob, Templater) disposaient déjà de leurs tests. Le travail a porté sur la cartographie
formelle par module, le comblement des trous d'edge cases de la liste de la spec (config JSON
corrompue, graphe vide, zéro/unicode/emoji en interpolation, retry réussi à la 2ᵉ tentative de bout
en bout, réponse AI tronquée, cohérence 404/403), et l'ajout front Vitest à valeur de régression.
**+13 tests backend, +36 tests front (3 fichiers), zéro bug découvert** — les garde-fous existaient
déjà, ils sont désormais épinglés par des tests (y compris le filet de sécurité `catch (Throwable)`
du runner, découvert non couvert par la mesure de couverture Xdebug). Suite finale 100 % verte :
backend **838 tests / 3 302 assertions / ~69 s** ; front 202 tests / 20 fichiers. Parallélisation (Paratest) vérifiée
possible mais sans gain local ; séquentiel conservé. Couverture réelle mesurée via Xdebug
(`XDEBUG_MODE=coverage`).

## Implementation

### Files Created

- `tests/Feature/Workflows/WorkflowCorruptedConfigTest.php` — 2 tests : config JSON corrompue en
  base (test-run → `invalid_config` métier, jamais 500 ni appel HTTP ; page d'édition → node servi
  avec `config = []`)
- `resources/js/lib/__tests__/nodeCategories.spec.ts` — 19 tests : contrat design-system des
  catégories de nodes (ordre fixe validé CVD, correspondance non triviale vers les tokens
  `--cat-1…5`, règle « jamais la couleur seule », replis)
- `resources/js/composables/__tests__/usePasswordValidation.spec.ts` — 6 tests : scoring du
  formulaire d'inscription (critères isolés, libellés, jauge) + `usePasswordMatch`
- `resources/js/composables/__tests__/useOtpCode.spec.ts` — 11 tests : logique des 6 cases OTP du
  défi 2FA (saisie, auto-avance, backspace, paste)
- `docs/reports/phase12/report.md` — ce rapport

### Files Modified

- `tests/Feature/Jobs/RunWorkflowJobTest.php` — +2 : graphe vide en run file (`no_trigger`) ;
  **retry E2E** (tentative 1 échec transient → release → tentative 2 → Completed, `attempt = 2`,
  journal 4 événements, lignes par attempt, events)
- `tests/Feature/Workflows/WorkflowTestRunTest.php` — +1 : workflow sans aucun node → test-run 200
  failed `no_trigger`
- `tests/Unit/Workflow/InterpolatorTest.php` — +4 : entier `0` inline (et `value()` retourne `0`
  strict), template vide, unicode/emoji autour d'un placeholder
- `tests/Unit/Ai/AiCompleterTest.php` — +1 : réponse JSON tronquée → retry avec rappel format puis
  récupération
- `tests/Feature/Workflows/WorkflowAuthorizationTest.php` — +1 : id de workflow inexistant → 404
  sur edit / update / destroy (cohérence 404 vs 403 documentée)
- `tests/Feature/Workflows/WorkflowExecutionIndexTest.php` — +1 : deep link vers une exécution
  inexistante → prop `execution = null` (comportement documenté phase 7, pas de 404)
- `tests/Unit/Workflow/WorkflowRunnerTest.php` — +1 : crash inattendu d'un handler (Throwable) →
  contenu en erreur `exception` générique, message technique jamais exposé, reste du run skip
  (filet de sécurité révélé par la mesure de couverture, cf. Gotchas)
- `README.md` — feuille de route : phase 12 → ✅
- `.knowledge/memory/maps/map-front-files.json` — 3 entrées specs (fichier non suivi git)

### Database Changes

Aucune migration. Cohérence de l'environnement de test vérifiée (`phpunit.xml`) : le moteur DB de
test est **sqlite `:memory:`** — le même moteur que le projet — avec `CACHE_STORE=array` et
`QUEUE_CONNECTION=sync`.

Directive factories/seeders (master §36) : la phase n'introduit aucune entité ni état nouveau —
aucune factory ni seeder à créer. L'existant est couvert : `DatabaseSeederTest` (8 tests) et
`TemplateSeederTest` (4 tests) prouvent l'idempotence et la complétude du seeding ; les factories
couvrent tous les états utiles (`WorkflowFactory::active()/trashed()`,
`WorkflowExecutionFactory::completed()/failed()/running()/cancelled()/webhook()/schedule()`,
états de logs, intégrations, credentials).

### Routes

Aucune route modifiée (pas de `wayfinder:generate` nécessaire).

### Frontend Changes

Uniquement 3 fichiers de test Vitest ; **aucun code de production modifié**. Style calqué sur les
17 specs existants (descriptions FR, `vi.stubGlobal`, fixtures typées `@/types`).

### Tests

Backend — les 6 familles d'edge cases de la spec, état avant/après :

| Edge case (spec)                                                                                                                               | Avant                      | Livré                                                              |
| ---------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- | ------------------------------------------------------------------ |
| Config JSON corrompue en base                                                                                                                  | absent                     | 2 tests (test-run + page édition)                                  |
| Workflow sans node                                                                                                                             | absent                     | 2 tests (test-run + run en file)                                   |
| Interpolation : 0, template vide, unicode/emoji                                                                                                | partiels                   | 3 tests (0 strict, `''`, emoji)                                    |
| Retry réussi à la 2ᵉ tentative                                                                                                                 | stoppait au release        | 1 test E2E complet                                                 |
| Réponse AI tronquée                                                                                                                            | cas « non-JSON » seulement | 1 test (tronqué → retry → récupéré)                                |
| Authorization ids inexistants                                                                                                                  | ids étrangers seulement    | 2 tests (workflow 404 ×3 actions ; exécution inconnue → prop null) |
| Crash inattendu d'un handler (Throwable)                                                                                                       | absent                     | 1 test (erreur `exception` générique, pas de fuite du message)     |
| Cycle A→B→C→A, auto-edge, edges dupliqués, node orphelin, X-Request-Id, payload 413, JSON invalide, usage manquant, provider à chaud, timeout… | **déjà couverts**          | — (non dupliqués)                                                  |

Front — 3 fichiers / 36 tests (voir Files Created). Candidats écartés : `flashToast.ts`,
`realtimeDebug.ts` (wrappers fins vue-sonner/Echo — les tester reviendrait à tester des mocks),
`utils.ts` (`cn`, trivial), `useWorkflowList`/`useTemplateGallery` (valeur réelle mais budget
« 1 à 3 fichiers » ; meilleurs candidats pour une suite éventuelle).

**Suite finale** : `php artisan test --compact` → 838 tests, 3 302 assertions, 57–69 s selon la
charge (baseline : 825 / 3 225 / ~86 s). `npm run test:unit` → 20 fichiers, 202 tests, verts.
Note : 783 déclarations `it()/test()` pour 837 exécutions — les datasets Pest (`->with([...])`,
déjà utilisés pour SSRF, validation de config, inputs de test-run) multiplient certaines entrées.

Cartographie couverture par module (tests déclarations ; couverture de lignes mesurée Xdebug,
`XDEBUG_MODE=coverage`, après la phase) — **total application : 96,2 %** :

| Module                                                                                                                  | Tests unitaires                                                                                                   | Tests feature / intégration                                                                                                                                  | Couverture                                                                                                                                                                                            |
| ----------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Moteur — graphe** : GraphValidator, GraphTraverser, WorkflowValidator                                                 | GraphValidatorTest (7), GraphTraverserTest (8), WorkflowValidatorTest (9)                                         | WorkflowGraphValidationTest (18), WorkflowActivationValidationTest (6), WorkflowGraphTest (3)                                                                | **100 %** (les trois)                                                                                                                                                                                 |
| **Moteur — exécution** : WorkflowRunner, ExecutionContext, RetryPolicy, ExecutionCancel                                 | WorkflowRunnerTest (12), …Cancellation (6), …NodeLogs (6), ExecutionContextTest (6), RetryPolicyTest (5)          | RunWorkflowJobTest (20), WorkflowRunTest (6), ExecutionCancelTest (5), WorkflowTestRunTest (13), …EndToEnd (2), AiWorkflowRun (4), ScheduleDispatch (7)      | ExecutionContext / RetryPolicy / ExecutionCancel **100 %** ; WorkflowRunner **98,6 %** (reste : 2 lignes défensives internes)                                                                         |
| **Interpolation** : Interpolator                                                                                        | InterpolatorTest (22)                                                                                             | (via handlers)                                                                                                                                               | **100 %**                                                                                                                                                                                             |
| **Catalogue / registre / mapper** : NodeCatalog, NodeHandlerRegistry, WorkflowGraphMapper                               | NodeCatalogTest (28), NodeHandlerRegistryTest (5)                                                                 | WorkflowEngineExtensibilityTest (1)                                                                                                                          | **100 %** (les trois)                                                                                                                                                                                 |
| **Handlers** (10 types)                                                                                                 | 95 tests (Ai 21, Http 23, Email 12, Condition 8, Transform 7, Input 6, Schedule 5, Manual 2, Output 2, Webhook 3) | intégrés aux runs E2E ci-dessus                                                                                                                              | 7 handlers **100 %** ; HttpHandler 95,2 %, AiNodeHandler 95,8 %, ConditionHandler 97,5 % ; enum `AiMode` 66,7 % (un cas jamais exercé)                                                                |
| **Logs** : ExecutionLogWriter, LogMessages, SecretRedactor                                                              | 7 + 7 + 8                                                                                                         | WorkflowExecutionLogModelTest (10), WorkflowExecutionModelTest (11), WorkflowRetentionTest (1)                                                               | SecretRedactor / LogMessages **100 %** ; LogWriter 92,5 %                                                                                                                                             |
| **Templates** : WorkflowTemplater                                                                                       | WorkflowTemplaterTest (12)                                                                                        | TemplateModel (10), Publish (5), Use (6), Gallery (4), Authorization (6), TemplateSeeder (4)                                                                 | **100 %**                                                                                                                                                                                             |
| **AI** : AiCompleter, AiProviderManager, providers ×4, AiJsonSchema, AiConfig                                           | 65 tests                                                                                                          | AiWorkflowRunTest (4)                                                                                                                                        | **100 %** partout sauf `MapsProviderErrors` 96,2 %                                                                                                                                                    |
| **Intégrations** : HttpClient (+pinning SSRF), IntegrationResolver, SmtpTransportFactory, ConnectionTester, EmailSender | 36 tests                                                                                                          | IntegrationConnection (5), IntegrationCrud (14), IntegrationPolicy (6)                                                                                       | HttpClient 87,1 % (branches réseau réelles), Resolver **100 %** ; ConnectionTester 79,3 %, SmtpTransportFactory 66,7 %, **EmailSender 40 %** (chemin SMTP réel non testé par design, cf. Limitations) |
| **Webhook** : WebhookHandler + endpoint                                                                                 | WebhookHandlerTest (3)                                                                                            | WebhookTrigger (14), WebhookEndpoint (7)                                                                                                                     | WebhookHandler **100 %**                                                                                                                                                                              |
| **Dashboard / notifications / présentation** : DashboardMetrics, NotificationPresenter, ExecutionPresenter              | DashboardMetricsTest (14)                                                                                         | DashboardTest (13), NotificationsEndpoints (9), ExecutionFailedNotification (12), WorkflowExecutionIndex (11)                                                | **100 %** (les trois)                                                                                                                                                                                 |
| **Authorization / sécurité** (Policies, hardening)                                                                      | —                                                                                                                 | WorkflowPolicy (11), WorkflowAuthorization (7+1), TeamPolicy (6), IntegrationPolicy (6), TemplateAuthorization (6), Security/* (31), TeamAccessHardening (4) | WorkflowPolicy / WorkflowTemplatePolicy **100 %**                                                                                                                                                     |
| **Socle** : auth Fortify, équipes, settings, seeders                                                                    | —                                                                                                                 | Auth (37), Teams (52), Settings (26), DatabaseSeeder (8)                                                                                                     | hors périmètre critique (socle Livewire/Fortify standard)                                                                                                                                             |

### Commands Executed

```bash
php artisan test --compact                     # état initial (825/3225/86 s) et final (837/3294/57–74 s)
vendor/bin/pest --parallel                     # 837/3294/73,1 s — verdict hygiène, non retenu
XDEBUG_MODE=coverage php artisan test --coverage
vendor/bin/pint --dirty --format agent         # passed
php artisan make:test --pest Workflows/WorkflowCorruptedConfigTest
npm run test:unit                              # 20 fichiers, 202 tests
npm run types:check                            # 0 erreur
npm run build                                  # ✓ built
composer show --direct                         # versions Pest/paratest
```

## Key Information

### Technical Decisions

- **Fakes Laravel plutôt que mocks internes** (règle de la spec, déjà la convention) :
  `Http::fake()/sequence()`, `Event::fake()`, `Http::preventStrayRequests()` ; seul Mockery
  contractuel sur `Job` (attempts/release) pour piloter la mécanique queue sans broker.
- **Pas de builder de graphe dédié** (`WorkflowGraphBuilder`) : `WorkflowNodeFactory`
  (`keyed/ofType/withConfig`) + `WorkflowEdgeFactory::between()` + le helper `graphPayload()`
  de `tests/Pest.php` couvrent tous les besoins sans duplication > 3. Critère d'extraction
  respecté : rien à extraire (un seul helper file-local `corruptNodeConfig`, 2 usages dans
  1 fichier, sous le seuil).
- **Exécution inconnue en deep link : comportement épinglé, pas changé** — la spec listait
  « 404 vs 403 cohérents » ; le comportement documenté phase 7 (prop `execution = null`,
  sélection gracieuse, pas de leak) est le contrat voulu : un test l'épingle plutôt que
  d'imposer un 404 (ce serait un changement de feature, hors périmètre phase testing).
- **Parallélisation mesurée, pas forcée** : `vendor/bin/pest --parallel` est **vert**
  (aucun état global ne fuit — bon signe d'hygiène) mais sans gain local (73,1 s vs 74,35 s :
  overhead de spawn des processus + SQLite). Séquentiel conservé par défaut ; `phpunit.xml` et
  `composer.json` inchangés.
- **Couverture réelle plutôt qu'estimation** : Xdebug est chargé en dev mais sans mode coverage ;
  `XDEBUG_MODE=coverage` l'active sans toucher à la config. (Le premier essai avec
  `--min=0` + filtre shell a perdu la sortie ; `tail` simple retenu.)
- **Front : valeur de régression d'abord** — le fichier `nodeCategories.ts` verrouille un
  contrat design-system (ordre CVD non trivial : Logique=`--cat-5`, IA=`--cat-3`,
  Actions=`--cat-4` — un « nettoyage » 1,2,3,4,5 casserait le test) ; les wrappers fins
  (vue-sonner, Echo) sont écartés sciemment.

### Gotchas & Solutions

- **Zéro bug découvert par les edge cases** — preuves apportées par tests : le cast `array`
  d'Eloquent rend `null` sur JSON invalide, le `WorkflowGraphMapper` coerce en `?? []`, le
  handler rejette en `invalid_config` (métier, jamais 500, jamais d'appel HTTP) ;
  `WorkflowController::edit` sert `config = []` ; `DispatchScheduledWorkflows` garde déjà
  `is_array($node?->config)`. Observation sans action : `WorkflowTemplater::duplicate()` copie
  la config nulle telle quelle (comportement identique à l'original, sans crash).
- **La mesure de couverture a révélé un trou que la lecture des noms de tests n'avait pas vu** :
  le filet de sécurité `catch (Throwable)` du `WorkflowRunner` (~30 lignes, erreur `exception`
  générique + `report()`, contrat master §26) n'était couvert par aucun des 11 tests existants du
  runner. Un test l'épingle désormais (handler de test qui lève — message technique jamais
  exposé). Leçon : **noms de tests ≠ comportements couverts** ; la couverture chiffrée attrape ce
  que l'inventaire déclaratif manque.
- **Piège SQLite (racine unique, deux symptômes)** : sans `ORDER BY`, les lignes reviennent dans
  l'ordre de l'index unique `(workflow_id, key)` — **ordre alphabétique des node keys**, pas
  l'ordre d'insertion. Toute assertion sur un graphe doit chercher par clé (`firstWhere`) ou
  trier (`sortKeys()`), jamais s'appuyer sur l'index de liste ; idem pour les lignes de journal
  regroupées par attempt (`orderBy('id')` + `sortKeys()`).
- **`Http::sequence()->throw()` n'existe plus en Laravel 13** — l'API des séquences est
  `pushFailedConnection(msg)` / `push(body, status)` (vérifié dans `vendor`,
  `ResponseSequence`) ; `Http::failedConnection()` existe pour un stub simple.
- **Xdebug chargé ≠ couverture disponible** : il faut `XDEBUG_MODE=coverage` (sinon
  « Code coverage driver not available »), et une exécution ~5× plus lente (~4 min) — à réserver
  aux mesures ponctuelles, pas à la boucle TDD.
- **Comptage tests** : `grep -c "it(|test("` donne 783 déclarations pour 837 exécutions
  (datasets Pest) — ne jamais raisonner en « nombre de tests écrits » pour la santé de la suite.

### Commands & Config

- Suite de référence : `php artisan test --compact` — **57–74 s** (objectif < 2–3 min : tenu,
  marge confortable).
- Rapide pendant le travail : `vendor/bin/pest tests/Unit` puis filtre `--filter=`.
- Couverture ponctuelle : `XDEBUG_MODE=coverage php artisan test --coverage`.
- Aucune variable `.env` ajoutée ou modifiée ; aucune config modifiée.

### Version Notes

- Pest 5.2.1 + `pestphp/pest-plugin-laravel` 5.0.1 ; Paratest 7.24.1 présent comme dépendance
  transitive de Pest (parallel sans config supplémentaire).
- Laravel 13 : API `ResponseSequence` = `pushFailedConnection()` / `push()` (voir Gotchas).
- Inertia Testing : `AssertableInertia::toArray()` expose les props sous `['props']` — utile pour
  des assertions non index-dépendantes (`firstWhere('key', …)`).
- Vitest : l'isolation par worker coûte ~2,4 s sur 20 fichiers (message officiel du runner :
  `isolate: false` serait plus rapide) — non retenu, l'isolation protège des fuites d'état.

## Future Ideas (Not Planned)

- **CI GitHub Actions** — la suite est verte, rapide (< 75 s) et parallélisable ; sur Linux le
  gain de `--parallel` sera réel (le spawn Windows l'annule localement). C'est le prochain
  multiplicateur de qualité naturel. À évaluer dès qu'un dépôt public est ouvert.
- **Seuil de couverture en CI** (`--min=…`) — la base chiffrée de cette phase rend un seuil
  défendable ; à n'activer qu'avec la CI.
- **Mutation testing** sur le moteur (plugin Pest `mutate` déjà installé) — mesurerait la
  qualité réelle des assertions au-delà des lignes couvertes. À essayer sur
  `app/Services/Workflow/**` uniquement (coût d'exécution élevé).
- **Tests e2e Playwright** — la spec de phase l'exclut explicitement ; les parcours critiques
  (builder, exécutions) sont couverts par feature tests Inertia, l'apport serait la régression
  d'interaction réelle. À évaluer phase 14 ou après.
- **Régression visuelle des maquettes** — les tokens et la palette CVD sont désormais verrouillés
  par tests unitaires ; un screenshot-diff sur les écrans clés compléterait. Outil à choisir
  (coût de maintenance non trivial).

## Known Limitations

- Pas de CI : le seuil de couverture et le parallélisme restent manuels et locaux.
- La couverture chiffrée est un instantané local (Xdebug) ; elle n'est pas intégrée à la boucle
  qualité (pas de badge, pas de seuil bloquant).
- **EmailSender 40 %, SmtpTransportFactory 66,7 %, ConnectionTester 79,3 %** : le chemin SMTP
  réel (Mailer Symfony construit depuis les credentials) n'est volontairement pas exercé en
  test — la frontière `$smtpSender` injectable (décision phase 5) le remplace dans tous les
  tests ; le couvrir reviendrait à tester Symfony Mailer, pas Faucon.
- **`AiMode` 66,7 %** : un cas de l'enum n'est exercé par aucun test (branche triviale de
  labellisation) — accepté, sans risque identifié.
- `flashToast.ts`, `realtimeDebug.ts`, `utils.ts`, `useWorkflowList.ts`, `useTemplateGallery.ts`
  restent sans tests (wrappers fins / budget de phase) — voir Future Ideas pour `useWorkflowList`
  en premier candidat d'une passe suivante.
- Pas de test e2e navigateur (hors périmètre de la phase, cf. spec).
- Paratest : vert mais sans gain sur cette machine (Windows) — la parallélisation n'est pas
  intégrée à la commande de référence.

## Next Phase

Phase 13 — **Optimisation & qualité** : N+1, payloads Inertia, code-splitting, mesures
avant / après. La base de tests consolidée de cette phase (837 tests verts, < 75 s) sert de
filet de sécurité aux optimisations à venir.
