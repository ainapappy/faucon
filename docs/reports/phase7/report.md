# Phase 7 — Execution & Queue

## Summary

La phase rend les exécutions **persistées et asynchrones** : entité `WorkflowExecution`
(migration, enum `ExecutionStatus`, factory à états), job `RunWorkflowJob` (queue database,
`tries`/`backoff` config-driven, `WithoutOverlapping` par exécution, `failed()` gardé) qui
**encapsule** le runner phase 4 (adapté par deux paramètres optionnels uniquement : hook
`$beforeNode` de cancellation entre nodes et budget `$timeoutMs` par run — zéro rupture des
tests phase 4). Les trois déclencheurs passent par une action unique `StartWorkflowRun` :
bouton « Exécuter » (liste + éditeur) et webhook (désormais **202** avec idempotence conservée
en amont) dispatchent, l'entrée scheduler unique (`DispatchScheduledWorkflows`, cron
`dragonmantank` déjà embarqué) dispatche les workflows planifiés dus. Cancellation
coopérative par flag cache (`ExecutionCancel`, endpoint `workflow-executions/{id}/cancel`),
retries par raison (`RetryPolicy` + config `retryable_reasons`), événements
Started/Completed/Failed. Front : page `workflows/executions/Index` fidèle à la maquette
(liste filtrable + paginée, **sheet** de détail avec timeline par node, polling Inertia
`only:['execution']` arrêté à l'état final), boutons Exécuter/Relancer/Annuler, badge de
statut CVD, maquette `executions.html` étendue en pré-requis (footer pending/running +
alertes). Seeders d'historique de démo. Suites finales : Pest **627/627** (2 181 assertions),
PHPStan niveau 7 **0 erreur**, Pint propre, Vitest **115 verts**, `types:check` et `build` OK.

## Implementation

### Files Created

Backend :

- `app/Enums/ExecutionStatus.php` — `Pending|Running|Completed|Failed|Cancelled`.
- `app/Enums/ExecutionTrigger.php` — `Manual|Webhook|Schedule`.
- `app/Models/WorkflowExecution.php` — relations workflow/team/user, casts, `isFinal()`.
- `app/Jobs/RunWorkflowJob.php` — job par id ; `$timeout` figé au dispatch ;
  `tries()`/`backoff()` relus à l'exécution ; middleware `WithoutOverlapping(exec:{id})`
  `expireAfter(timeout+60)` ; transitions D1 (statuts, timings par tentative, `attempt`) ;
  `failed()` en update gardé (jamais d'écrasement d'un état final).
- `app/Actions/Workflows/StartWorkflowRun.php` — création `pending` + dispatch, partagé par
  les 3 déclencheurs.
- `app/Actions/Workflows/DispatchScheduledWorkflows.php` — workflows actifs à
  `trigger.schedule` dont le cron est dû (`Cron\CronExpression::isDue(now())`) ; cron
  invalide → `report()` + skip, jamais de crash de l'entrée planifiée.
- `app/Services/Workflow/WorkflowGraphMapper.php` — mapping graphe → shapes moteur, extrait
  1:1 de `TestRunWorkflow` (un seul lieu de vérité, partagé test-run/job).
- `app/Services/Workflow/ExecutionCancel.php` — clé/put/has/forget du flag cache
  (`exec:cancel:{id}`, TTL config).
- `app/Services/Workflow/RetryPolicy.php` — `isRetryable(ExecutionResult)` : toutes les
  raisons ∈ allow-list config.
- `app/Services/Workflow/Handlers/Trigger/ScheduleHandler.php` — `validate()` (cron requis +
  valide, partagé avec la sauvegarde de graphe) ; output `triggered_at` + `cron`.
- `app/Policies/WorkflowExecutionPolicy.php` — `viewAny/view/cancel` (cancel =
  permission `WorkflowUpdate`, arbitrage A3).
- `app/Events/Workflows/{WorkflowExecutionStarted,Completed,Failed}.php`.
- `app/Http/Requests/Workflows/RunWorkflowRequest.php` — `input` objet optionnel borné
  (byte-limit du webhook réutilisée).
- `app/Http/Controllers/Workflows/RunWorkflowController.php` — POST `workflows/{workflow}/run`
  (Gate `update`, 422 si non actif, redirect back + toast flash avec lien).
- `app/Http/Controllers/Workflows/WorkflowExecution/WorkflowExecutionController.php` —
  index paginé (filtres `workflow_id`/`status`/`q`, prop `execution` = deep-link
  `?execution=`, scoped team, Policy view).
- `app/Http/Controllers/Workflows/WorkflowExecution/CancelWorkflowExecutionController.php` —
  pose le flag (200 `cancelling`) ; final → 200 explicite, jamais d'erreur ; jamais d'écriture
  de statut.
- `database/migrations/2026_09_20_054504_create_workflow_executions_table.php` — schéma D11
  (+ `user_id` nullable, arbitrage A6) ; index `status` et `(workflow_id, status, created_at)`.
- `database/factories/WorkflowExecutionFactory.php` — états `running/completed/failed/
cancelled/webhook/schedule`.
- `database/seeders/DemoWorkflowExecutionSeeder.php` — historique réaliste par workflow de
  démo (timeline construite depuis le vrai graphe), idempotent.

Frontend :

- `resources/js/types/executions.ts` — `WorkflowExecutionStatus/Trigger`, liste, détail,
  `PaginatedExecutions` (paginator Inertia aplati), `ExecutionFilters`, `WorkflowOption`.
- `resources/js/composables/useExecutionPolling.ts` — `usePoll(1500, only:['execution'],
autoStart:false)` + watcher : démarre sur statut non final, stoppe à l'état final.
- `resources/js/components/executions/ExecutionStatusBadge.vue` — 5 statuts, icône +
  libellé + token (règle CVD), spinner animé sur `running`.
- `resources/js/lib/executionFormat.ts` — `formatDurationMs` (miroir `$fmt.ms`),
  `formatExecutionDate` (« Aujourd'hui · 12:04 »), `formatTrigger`.
- `resources/js/pages/workflows/executions/Index.vue` — historique paginé filtrable + sheet
  de détail (résumé, alertes par statut, timeline avec barres de durée et JSON repliable,
  journal en placeholder phase 8, footer contextuel Relancer/Annuler), deep-link
  `?execution=`, pagination et filtres en reloads partiels.

### Files Modified

- `app/Services/Workflow/WorkflowRunner.php` — `run(..., ?callable $beforeNode, ?int
$timeoutMs)` ; statut `cancelled` ; message de timeout neutre (« L'exécution a dépassé… »).
- `app/Data/Workflow/ExecutionResult.php` — commentaire du statut (DTO inchangé).
- `app/Actions/Workflows/TestRunWorkflow.php` — délègue le mapping au `WorkflowGraphMapper`
  (comportement inchangé, tests phase 4/5/6 verts).
- `app/Http/Controllers/Workflows/WebhookController.php` — fin **async** : `StartWorkflowRun`
    - **202** `{status: 'pending', execution_id}` ; amont inchangé (lookup hash, éligibilité 404
      uniforme, bornes payload, dédup `WebhookRequest`).
- `app/Models/Workflow.php` — relation `executions()`.
- `app/Providers/AppServiceProvider.php` — enregistrement `ScheduleHandler` au registre.
- `app/Http/Requests/Workflows/SaveWorkflowGraphRequest.php` —
  `validateTriggerScheduleCron` : délégation au handler (un lieu de vérité avec le moteur).
- `routes/web.php` — `workflows.run`, `workflow-executions.index`, `workflow-executions.cancel`.
- `routes/console.php` — entrée unique `Schedule::call(DispatchScheduledWorkflows)
->everyMinute()->withoutOverlapping()->name('workflow-schedule-triggers')`.
- `config/workflows.php` — section `execution` (`timeout_ms`, `max_tries`, `backoff`,
  `cancel_ttl`, `retryable_reasons`).
- `.env.example` — `# WORKFLOW_EXECUTION_TIMEOUT_MS/MAX_TRIES/CANCEL_TTL` documentés.
- `resources/js/types/workflows.ts` — `ExecutionStatus` renommé `EngineRunStatus`
  (élargi à `cancelled`, retour du runner) ; `ExecutionResult.status` suit.
- `resources/js/types/index.ts` — export `./executions`.
- `resources/js/pages/workflows/Index.vue` — `runNow` repointé : POST async (useHttp) + toast
  avec lien vers la page Exécutions (remplace le renvoi ?run=1 du builder, AM2 — cf.
  Technical Decisions).
- `resources/js/pages/workflows/Edit.vue` + `BuilderTopbar.vue` — bouton « Exécuter »
  (workflows actifs uniquement) à côté de « Tester » (qui reste le test-run synchrone).

Maquette (design-first, pré-requis du lot front — arbitrage A5) :

- `.knowledge/design/executions.html` — helper `status()` + entrée `cancelled` ; alertes
  `pending` (« En attente d'exécution ») et `cancelled` ; footer de sheet étendu aux états
  `pending/running` avec bouton destructif « Annuler l'exécution » + méthode `cancelRun()` ;
  une ligne de démo annulée. Zéro token CSS nouveau.

Tests (nouveaux) :

- `tests/Feature/Workflows/WorkflowExecutionModelTest.php` (11) — enums, factory, relations,
  `isFinal()`, casts.
- `tests/Unit/Workflow/WorkflowRunnerCancellationTest.php` (6) — hook avant-node (avant le
  trigger, entre deux nodes), clés reçues dans l'ordre, skipped, BC sans hook, budget
  overridable.
- `tests/Feature/Jobs/RunWorkflowJobTest.php` (12) — succès (statuts+result+events), échec
  non-retryable (`unknown_type`), retryable → re-`pending` + `release(30)` (mock
  `Illuminate\Contracts\Queue\Job`), tentatives épuisées → `failed` définitif, flag avant run
  → `cancelled` sans événements, flag levé en cours de run → arrêt entre deux nodes avec
  résultat partiel, workflow inactif/supprimé → `workflow_inactive`, exécution finale
  intouchable, `failed()` une fois (gardé), propriétés config (`tries/backoff/timeout/
middleware`), colonne `attempt` = tentative queue.
- `tests/Feature/Workflows/WorkflowRunTest.php` (6) — run manuel (pending+dispatch+user),
  input stocké, 422 sur draft sans dispatch, membre autorisé, intruse 403, 404 hors équipe.
- `tests/Feature/Workflows/ExecutionCancelTest.php` (5) — flag posé, final → 200 sans flag,
  boucle complète endpoint→job→`cancelled`, 403 intruse, 404 hors équipe.
- `tests/Feature/Workflows/WorkflowExecutionIndexTest.php` (6) — pagination 15/page
  (paginator aplati), filtres workflow/statut, recherche id/nom, deep-link avec détail,
  invité → login.
- `tests/Feature/Workflows/ScheduleDispatchTest.php` (7) — dû à la minute (`Carbon::setTestNow`),
  hors minute rien, inactifs/supprimés rien, cron invalide skip, exécution créée pour le bon
  workflow, run planifié e2e avec `cron`+`triggered_at` en sortie du trigger, entrée
  scheduler enregistrée (`schedule:list`).
- `tests/Unit/Workflow/RetryPolicyTest.php` (5) — matrice des raisons (retryables :
  réseau/provider ; non : validation, SSRF, `http_request_failed`, `timeout`), mélange,
  allow-list config-driven.
- `tests/Unit/Workflow/Handlers/ScheduleHandlerTest.php` (5) — validate (manquant/invalide/
  valide), output déterministe sous `Carbon::setTestNow`.

Tests modifiés :

- `tests/Feature/Workflows/WebhookTriggerTest.php` — contrat 202 (exécution `pending`
    - `Queue::assertPushed`), doublon → 200 `duplicate` + un seul job, sans `X-Request-Id` →
      2 dispatchs, rate-limit sur 202.
- `tests/Unit/Workflow/WorkflowRunnerTest.php` — libellé du timeout aligné sur la nouvelle
  formulation neutre.

### Database Changes

Une table : `workflow_executions` (schéma D11). Écarts assumés au croquis de phase7.md :
`user_id` nullable (A6 — audit des runs manuels, utile dès la phase 10) et `error` qui
persiste aussi `message` (affichage sheet) sous forme camelCase `{nodeKey, type, reason,
message}`, cohérente avec `result.nodes[].error` (le croquis écrivait `node_key` snake ;
le camelCase est aligné sur `ExecutionError::toArray()` et le reste du payload `result`).
`config/workflows.php` porte la nouvelle section `execution` ; aucune migration pour
`trigger.schedule` (cron dans `workflow_nodes.config`, conformément à la phase).

### Routes

`POST {team}/workflows/{workflow}/run` (`workflows.run`) ·
`GET {team}/workflow-executions` (`workflow-executions.index`) ·
`POST {team}/workflow-executions/{execution}/cancel` (`workflow-executions.cancel`).
`php artisan wayfinder:generate` exécuté (actions + `routes/workflow-executions/`).

### Frontend Changes

Voir Files Created/Modified. Points de contrat : props `executions` (paginator **aplati**
`data`+`total` — pas de wrapper `meta`), `execution` (deep-link + cible du polling),
`workflows`, `filters`, `permissions` ; polling `usePoll` `only:['execution']` arrêté sur
état final ; deep-link `?execution={id}` servi par les toasts (useHttp + toast local — le
flash serveur `toast.href` reste un canal de secours non rendu par `initializeFlashToast`,
qui n'affiche que `message`) ; badge statut = icône + libellé + couleur (jamais la couleur
seule) ; le test-run du builder reste synchrone (arbitrage A2).

### Tests

Backend : **69 nouveaux tests** — total suite **627/627, 2 181 assertions**. Front : **+13
specs Vitest** (executionFormat 8, useExecutionPolling 5) — total **115 verts**. PHPStan
niveau 7 : 0 erreur. Pint propre. `types:check` OK, `npm run check` OK, `build` OK.

### Commands Executed

- `search-docs` (Boost) : queues (`WithoutOverlapping`/locks, `Tries`/`tries()`,
  `backoff()`, `retryUntil`), scheduler, Inertia v3 (`usePoll`, partial reloads,
  prop evaluation model) — avant le code.
- `composer show`/vendor : `dragonmantank/cron-expression` v3.4 (dépendance du framework —
  `CronExpression::factory()->isDue()` vérifiés), `InteractsWithQueue::attempts()` (pluriel),
  sérialisation Inertia des paginateurs (aplatie).
- `php artisan make:model -m -f`, `make:enum` ×2, `make:controller`/`make:request` ajustés ;
  `php artisan route:list`, `php artisan schedule:list` (entrée visible :
  « Dispatch due scheduled workflow runs »), `php artisan migrate`.
- Boucles Red → Green par lot (`vendor/bin/pest` périmètre étroit) ; `Queue::fake` +
  `->handle()` direct + mock `Illuminate\Contracts\Queue\Job` pour `release()` ;
  `Carbon::setTestNow` à la minute pour les crons.
- Finaux : `vendor/bin/pint --dirty --format agent` ; `php artisan test --compact` ;
  `vendor/bin/phpstan analyse --no-progress` ; `php artisan wayfinder:generate` ;
  `npm run test:unit|types:check|check|build` ; `npx vp check --fix`.

## Key Information

### Technical Decisions

Arbitrages A1–A6 **validés par l'utilisateur** (§0 du plan) ; décisions D1–D16 détaillées
dans `.knowledge/memory/plans/phase7-implementation-plan.md` (prime sur phase7.md). Résumé :

- **Le job encapsule le runner** : adaptation par paramètres optionnels (`$beforeNode`,
  `$timeoutMs`) — le runner reste pur (ni base ni cache), le job mappe résultat → transitions
  persistées. Les tests phase 4 n'ont pas bougé (hormis le libellé FR du timeout).
- **Une action `StartWorkflowRun`** pour les 3 déclencheurs : création `pending` + dispatch
  inséparables ; l'idempotence webhook reste EN AMONT (`WebhookController`), donc pas de
  double dispatch possible.
- **Double mode (A2)** : test-run de l'éditeur synchrone (tiroir, budget 5 s) ; « Exécuter »
  async (liste + éditeur). Le `runNow` de la liste, qui ouvrait la modale de test du builder
  (?run=1, amendement AM2 de la phase 3), pointe désormais vers l'exécution en queue — le
  test unitaire reste accessible depuis l'éditeur ; c'est l'effet attendu de la phase 7
  (« Run : bouton Exécuter → toast de dispatch + lien »).
- **Webhook 202** (changement de contrat assumé) : l'ancienne réponse portait le résultat
  complet — incompatible avec l'async ; corps : `{status: 'pending', execution_id}`.
- **Cancellation coopérative** : endpoint → flag cache uniquement ; le job honore le flag au
  début d'une tentative ou entre deux nodes via le hook du runner ; un node en cours (IA
  30 s) ne peut pas être interrompu ; flag nettoyé quand honoré, TTL sinon.
- **Retries par raison, pas par exception** : le runner transforme les échecs de nodes en
  résultats (jamais d'exception) — donc `handle()` ne throw pas et le mécanisme de retry du
  framework ne court-circuite pas la politique ; `RetryPolicy.isRetryable` (allow-list
  config) + `$this->release(backoff)` + re-`pending`. `max_tries=2` conservateur : un retry
  ré-exécute TOUT le graphe (les emails partiraient deux fois).
- **`failed()` en update gardé** (`whereNotIn status final`) : un kill post-complétion ne
  ré-écrit jamais un état final.
- **Timings par tentative** (D12) : `started_at/finished_at/duration_ms` = fenêtre de la
  dernière tentative ; réinitialisés au re-`pending`. `attempt` = compteur de tentatives
  queue (`$this->attempts()`), visible dans l'UI (maquette : « Tentative »).
- **`WithoutOverlapping(exec:{id})`** : anti-recouvrement d'une même exécution
  (timeout/release) ; `expireAfter(timeout+60)` pour ne jamais bloquer après un kill ; les
  drivers `array`/`database` supportent les locks (tests OK).
- **Un seul handler `ScheduleHandler`** : sa `validate()` sert à la fois au moteur et à la
  sauvegarde de graphe (délégation depuis `SaveWorkflowGraphRequest`) — un seul lieu de
  vérité du cron ; l'entrée scheduler est unique (une requête par minute, `isDue` par
  workflow), pas une entrée par workflow.
- **Schéma** : `team_id` dénormalisé (scoping direct des requêtes, index
  `(workflow_id, status, created_at)`) ; `user_id` nullable (A6) ; `error` camelCase avec
  `message` (superset du croquis, cohérent avec `result`).
- **Page Exécutions = liste + sheet** (A1) : la maquette ne connaît pas de page Show ; le
  deep-link `?execution=` matérialise le lien des toasts. Journal de la sheet en placeholder
  (table fine des logs = phase 8).
- **TS** : l'ancien `ExecutionStatus` (test-run) renommé `EngineRunStatus` et élargi à
  `cancelled` (le runner peut maintenant le retourner) ; le nouveau statut persisté s'appelle
  `WorkflowExecutionStatus` — zéro collision.

### Gotchas & Solutions

- **`InteractsWithQueue::attempts()`** (pluriel) — pas `attempt()` ; et `release()` lève
  hors worker : mock `Illuminate\Contracts\Queue\Job` (`attempts()` + `release()` attendu)
  injecté via `setJob()` pour tester le chemin de retry en direct.
- **`Event::assertDispatched` n'est pas chaînable** (retour void) — assertions séparées.
- **Tests `Unit/` sans RefreshDatabase** (Pest.php : `->use(RefreshDatabase)->in('Feature')`)
  — le test du job, DB-dependent, vit sous `Feature/Jobs/` (convention : tests avec base en
  Feature, cf. `WorkflowModelTest`).
- **`ValidationException` sur POST non-JSON** → 302 + erreur en session, pas 422 : les tests
  d'autorisation/échec utilisent `postJson` quand un 422/403 est attendu.
- **La matrice de rôles donne `WorkflowUpdate` à TOUS les rôles** (Owner/Admin/Member) — le
  403 sur run/cancel n'est atteignable que par un utilisateur hors équipe ; c'est ce cas qui
  est testé (intruse), pas un « membre sans permission ».
- **Paginator Inertia aplati** : `executions.total`, pas `executions.meta.total`
  (`LengthAwarePaginator::toArray()` passe par `resolvePropertyInstances`) — le test
  l'épingle pour la phase 10.
- **`Cron\CronExpression::isDue()`** : précision minute — sous `Carbon::setTestNow`,
  positionner à `09:00:00` (pas `09:00:59`) ; timezone = TZ applicative (documenté en
  limitation).
- **PHPStan niveau 7** : `findOrFail()`/`find()` renvoient une union `Workflow|Collection`
  (propriété `team_id` introuvable) → lire la colonne via `value('team_id')` dans la factory ;
  les collections → `list` exigent un `foreach` d'accumulation (ni `values()->all()` ni
  `map()->all()` ne satisfont `list<...>`).
- **Wayfinder** : contrôleurs à méthodes (`[Class, 'index']`) génèrent des modules à exports
  nommés ; invokables, un export **par défaut** (`import runWorkflow from …`) — mélanger les
  deux casse `types:check`.
- **`useHttp` + `FormDataType`** : un payload `{input: Record<string, unknown>}` viole la
  contrainte générique (valeurs `unknown`) — le POST d'exécution n'a pas besoin de payload ;
  pour « Relancer », cast local `Record<string, FormDataConvertible>`.
- **Corruptions d'écriture** (environnement, hors projet) : plusieurs fichiers écrits cette
  phase sont sortis tronqués/mutés en cours de route (enum, factory, contrôleur, tests) —
  détection systématique par `php -l` + relecture, correction par heredoc chunked. Aucun
  contenu corrompu ne subsiste (suites vertes + PHPStan 0).
- **Workers long-lived** : `tries()`/`backoff()` relisent la config à l'exécution ;
  `$timeout` est une propriété sérialisée (figée au dispatch — documenté) ; les handlers
  (directive z.ai) lisent `config()` à chaque exécution — rien à changer côté IA.

### Commands & Config

`config/workflows.php` § `execution` : `timeout_ms` = `WORKFLOW_EXECUTION_TIMEOUT_MS`
(120 000), `max_tries` = `WORKFLOW_EXECUTION_MAX_TRIES` (2), `backoff` = `[30]` (config
only), `cancel_ttl` = `WORKFLOW_EXECUTION_CANCEL_TTL` (3 600), `retryable_reasons` =
`['network_error', 'provider_timeout', 'provider_unreachable', 'provider_rate_limited']`.
`.env.example` : les 3 entrées documentées (commentées) ; `QUEUE_CONNECTION=database` déjà
présent. Commandes utiles : `php artisan schedule:list` (entrée `workflow-schedule-triggers`),
`php artisan queue:work` (traitement des runs en local), `php artisan migrate:fresh --seed`
(historique de démo inclus).

### Version Notes

- Laravel 13 : attributs `#[Tries]/#[Timeout]` disponibles, mais méthodes `tries()`/`backoff()`
  retenues pour lire la config à l'exécution ; `WithoutOverlapping` requiert un driver de
  cache à locks (`database`/`array` OK) ; `InteractsWithQueue::attempts()` retourne 1 hors
  worker (utile aux tests directs).
- Inertia v3 : `usePoll(interval, () => ({ only: [...] }), { autoStart: false })` avec
  options réévaluées à chaque tick + `start/stop/polling` ; throttle 90 % en onglet
  arrière-plan (comportement par défaut retenu) ; les reloads partiels `only` filtrent côté
  serveur par nom de prop.
- `dragonmantank/cron-expression` v3 : `factory()` + `isDue($time)` toujours présents ;
  dépendance directe du framework (aucun composer.json touché).

## Future Ideas (Not Planned)

- **Horizon + Redis** : dashboards de files, priorités, rate-limiters de queue — évaluer au
  premier signe de contention (master §15 : progressivité).
- **Snapshots de graphe par exécution** : figer nodes/edges au dispatch pour des relectures
  historiquement exactes (aujourd'hui le run lit le graphe à l'exécution) — à coupler à la
  phase 8 (logs) si le besoin d'audit se précise.
- **« Retry programmé dans X min »** (maquette, indicatif) : afficher le `availableAt` du job
  en backoff sur les exécutions re-`pending` — nécessiterait de lire `jobs` (available_at).
- **Reprises partielles (retry par node)** : reprendre un run à partir du node en échec avec
  les sorties déjà produites — grand gain pour les graphes coûteux (IA), coût moteur notable.
- **Récurrences avancées (RRULE)** au-delà du cron, et webhooks de statut sortants
  (notification d'échec vers un endpoint client).
- **Dead-letter queue / alerting** sur les `failed()` brutaux — naturel en phase 8/10
  (notifications in-app).
- **Polling de la liste** quand des exécutions actives existent (aujourd'hui seul le détail
  poll) — via `only:['executions']` conditionnel.

## Known Limitations

- Pas d'Horizon/Redis : queue `database` unique, pas de priorités ; les runs partagent la
  queue des autres jobs.
- Cancellation coopérative : l'arrêt survient **entre deux nodes** — un node en cours (IA
  jusqu'à 30 s, HTTP 10 s) termine avant ; si le job est tué par le timeout avant tout
  entre-nodes, l'exécution passe par `failed()` (`job_failed`).
- Retries = ré-exécution complète du graphe : effets de bord non idempotents (emails)
  répétés au retry — `max_tries=2` (1 retry) par défaut.
- Minutes cron manquées (scheduler/worker arrêtés) non rattrapées ; crons évalués en TZ
  applicative.
- Timings = fenêtre de la dernière tentative ; pas de durée cumulée multi-tentatives.
- Pas de snapshot de graphe : une édition entre le dispatch et le run est prise en compte.
- Pas d'événement pour `cancelled` ni pour les re-`pending` (les listeners arrivent en
  phase 8 ; l'ajout sera guidé par leurs besoins).
- `result` JSON non plafonné (bornes par node existantes : webhook 64 Ko, HTTP 1 Mo).
- Journal de la sheet en placeholder (logs par node = phase 8) ; le flash serveur `toast`
  n'affiche pas encore de lien (le lien passe par les toasts useHttp côté front).
- Le tri « Exécutions » de la liste workflows reste sans effet (compteur : phase 10).

## Next Phase

Phase 8 — Logs & monitoring : logs par node (`WorkflowExecutionLog`, alimentée par
`result`/événements désormais persistés), redaction des secrets, timeline d'exécution
complète (le placeholder « Journal » de la sheet est prêt à recevoir les logs), rétention.
