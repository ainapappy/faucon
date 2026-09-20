# Phase 8 — Logs & monitoring

## Summary

La phase rend le parcours d'exécution **auditable node par node** : nouvelle table
`workflow_execution_logs` (**source de vérité** du parcours — une row par node PAR tentative
plus des rows d'événements), écrite uniquement par `RunWorkflowJob` via `ExecutionLogWriter`
(upsert sur `(execution, attempt, node_key)`). Le runner phase 4/7 reste **pur** : adaptation
par ajouts optionnels uniquement — `input` ajoutée à `NodeRunResult` (elle était déjà calculée
dans `NodeContext`) et hook `$onNodeResult` sur le pattern `$beforeNode` — BC totale des tests
existants. La **redaction des secrets** (`SecretRedactor`, masquage par clés exactes et par
suffixes, troncature des scalaires, cap de profondeur et plafond 64 Ko par colonne JSON)
s'applique avant toute persistance des rows de log. Le JSON `result` de `workflow_executions`
est **allégé** (résumé sans `output`/`input` via `toSummaryArray()`) et **retiré de la prop
`execution`** — le front lit désormais `execution.logs`. La **rétention** repose sur
`MassPrunable` (logs 30 j, exécutions finales uniquement 90 j) et le `model:prune` quotidien
déjà planifié (aucune entrée scheduler ajoutée). Front : la sheet Exécutions est portée à la
fidélité maquette — timeline « Parcours par node » avec dépliage **Entrée / Sortie / Erreur**,
**Journal** horodaté (teintes par niveau, séparateurs « Tentative N »), caption de rétention ;
polling `only:['execution']` inchangé (les logs arrivent avec la prop pendant
pending/running). Suites finales : Pest **679/679** (2 424 assertions), PHPStan niveau 7
**0 erreur**, Pint propre, Vitest **127/127** (+12), `types:check` et `build` OK,
`model:prune --pretend` détecte bien les deux modèles prunables.

## Implementation

### Files Created

Backend :

- `database/migrations/2026_09_20_113316_create_workflow_execution_logs_table.php` —
  `workflow_execution_id` FK cascade, `attempt` (default 1), `kind` (node|event), `level`
  (info|ok|error), `node_key` varchar(64) nullable, `node_type`/`node_name`/`status`/`duration_ms`
  nullable, `message` text, `input`/`output`/`error` JSON, `offset_ms`, index
  `(workflow_execution_id, attempt)` et index unique `(workflow_execution_id, attempt,
  node_key)` nommé **court** `workflow_execution_logs_unique` (cf. Gotchas) — les rows
  d'événements (`node_key` NULL) ne participent pas à l'unicité.
- `app/Enums/ExecutionLogKind.php` — `Node|Event`.
- `app/Enums/ExecutionLogLevel.php` — `Info|Ok|Error`.
- `app/Models/WorkflowExecutionLog.php` — `#[Fillable]`, casts enums + array, relation
  `execution()` (FK explicite), `MassPrunable` + `prunable()` (fenêtre config).
- `app/Services/Workflow/Log/SecretRedactor.php` — masquage récursif par clés (exact
  insensible à la casse + suffixes, config `workflows.logs`), structure préservée, troncature
  des chaînes (> `max_string_chars`, ellipsis « … »), cap de profondeur 32 (marqueur
  « [profondeur max] »), config relue à chaque appel.
- `app/Services/Workflow/Log/ExecutionLogMessages.php` — phrases FR du journal, figées à
  l'écriture (started/completed/failed/retryScheduled/cancelled, trigger label
  Manuel/Webhook/« Planifié — cron {expr} » — **jamais le chemin webhook**, qui porte le
  token) ; format de durée miroir de `formatDurationMs` front (« 860 ms », « 4,1 s »,
  « 2 min 05 s »).
- `app/Services/Workflow/Log/ExecutionLogWriter.php` — `openAttempt` (rows `queued` pour
  tous les nodes de la tentative) / `recordNode` (upsert ok/error, redaction + plafond
  64 Ko **par colonne** avec marqueur `{_payload_bytes, _note}`) / `recordEvent` /
  `closeAttempt` (les jamais exécutés passent `skipped`) ; `offset_ms` = delta absolu depuis
  le début de **la tentative**.
- `database/factories/WorkflowExecutionLogFactory.php` — default (event info) + états
  `queued`/`okNode`/`errorNode`/`skipped` (offsets et durées plausibles).

Frontend :

- `resources/js/lib/executionLogs.ts` — `buildTimeline(execution)` (rows `kind==='node'` de
  la tentative courante, ordre id asc, type-guard `ExecutionTimelineNode` qui encode la
  garantie back : ces rows portent toujours nodeKey/type/name/status) ;
  `buildJournal(execution)` (toutes les rows portant un message, groupées par tentative,
  séparateurs « Tentative N » seulement si `attempt > 1`, `t = formatDurationMs(offsetMs)`) ;
  `durationBarWidth` (proportion, plancher 6 %). Aucun remasquage côté front : les payloads
  redactés passent tels quels (test dédié).
- `resources/js/lib/__tests__/executionLogs.spec.ts` — 12 specs (timeline multi-nodes
  queued/skipped, filtre par tentative, vide sans logs, payloads masqués non altérés ;
  journal multi-tentatives avec/sans séparateurs, rows sans message exclues ; largeurs de
  barre).

Tests (nouveaux) :

- `tests/Feature/Workflows/WorkflowExecutionLogModelTest.php` (10) — enums, factory, casts,
  relation, `prunable()`.
- `tests/Unit/Workflow/SecretRedactorTest.php` (8) — clés exactes/suffixes, casse,
  imbrication, troncature, profondeur, config-driven.
- `tests/Unit/Workflow/ExecutionLogMessagesTest.php` (7) — phraséologie FR, labels de
  déclencheur (webhook SANS chemin), durées.
- `tests/Unit/Workflow/WorkflowRunnerNodeLogsTest.php` (6) — `input` par node, hook
  `$onNodeResult` (y compris node en erreur, jamais pour skipped), BC sans hook.
- `tests/Unit/Workflow/ExecutionLogWriterTest.php` (7) — openAttempt/recordNode/closeAttempt,
  upsert idempotent, redaction appliquée, plafond 64 Ko.
- `tests/Feature/Workflows/WorkflowRetentionTest.php` — `model:prune` élimine logs > 30 j et
  exécutions finales > 90 j, épargne les exécutions non finales.

### Files Modified

- `app/Data/Workflow/NodeRunResult.php` — + `input` (default `[]`), `toArray()` l'expose,
  + `toSummaryArray()` (sans `output`/`input`).
- `app/Data/Workflow/ExecutionResult.php` — + `toSummaryArray()`.
- `app/Services/Workflow/WorkflowRunner.php` — paramètre optionnel `?callable $onNodeResult`,
  appelé après CHAQUE node exécuté (y compris les deux chemins d'exception), jamais pour les
  skipped ; `input` passée aux trois sites de construction de `NodeRunResult` ; zéro écriture
  base.
- `app/Jobs/RunWorkflowJob.php` — `handle()` gagne un 3ᵉ paramètre `ExecutionLogWriter` ;
  événement Started dispatché AVANT `openAttempt` (chronologie du journal) ; hook
  `$onNodeResult` → `recordNode` ; `persistResult` : `closeAttempt` + événement final selon
  la branche (completed/failed/retry « Retry programmé (tentative N/M dans D s) »/cancelled) ;
  `result` écrit via `toSummaryArray()` ; `persistFailed`/`persistCancelled` : événement seul
  sur écriture effective ; `failed()` : `closeAttempt` + événement failed une seule fois
  (writer résolu via `app()`) ; `triggerLabel` sans chemin webhook.
- `app/Models/WorkflowExecution.php` — relation `logs()` (HasMany ordonné par id),
  `MassPrunable` + `prunable()` filtrant les **états finaux uniquement**.
- `app/Http/Controllers/Workflows/WorkflowExecution/WorkflowExecutionController.php` —
  eager-load `logs` sur la sélection uniquement, projection plate camelCase `toLogRow`
  (D8), `result` retiré de la prop, nouvelle prop racine `logs_retention_days`.
- `config/workflows.php` — section `logs` (D6 : `retention_days`, `max_string_chars`,
  `max_json_bytes`, `redacted_keys`, `redacted_key_suffixes`) + `execution.retention_days`.
- `.env.example` — `WORKFLOW_LOGS_RETENTION_DAYS`, `WORKFLOW_LOGS_MAX_STRING_CHARS`,
  `WORKFLOW_LOGS_MAX_JSON_BYTES`, `WORKFLOW_EXECUTIONS_RETENTION_DAYS` documentés.
- `database/seeders/DemoWorkflowExecutionSeeder.php` — rows de logs cohérentes par statut
  (completed complet ; failed sur 2 tentatives + « Retry programmé » ; running avec step
  queued ; cancelled avec step skipped ; pending sans rows), `result` allégé, payload webhook
  de démo montrant `'api_token' => '[masqué]'`.

Frontend :

- `resources/js/types/executions.ts` — `WorkflowExecutionLogKind/Level/NodeLogStatus`,
  `WorkflowExecutionLogEntry` (miroir exact de la projection contrôleur) ;
  `WorkflowExecutionDetail` : `result` retiré, `logs` ajouté.
- `resources/js/types/workflows.ts` — `NodeRunResult` gagne `input?` (additif, contrat tiroir
  test-run — le tiroir n'est pas enrichi, hors scope).
- `resources/js/pages/workflows/executions/Index.vue` — timeline alimentée par
  `buildTimeline` (dots **soft** de la maquette : `success-soft`/`danger-soft`/`info-soft` +
  icône colorée, spinner sur queued, « · » sur skipped ; barre rendue seulement quand
  `durationMs` est connu ; dépliage = blocs « Entrée »/« Sortie » via `formatJson` + bloc
  « Erreur ») ; journal alimenté par `buildJournal` (placeholder supprimé, teintes par
  level, séparateurs « Tentative N ») ; caption « Rétention : {n} jours · les secrets sont
  masqués automatiquement dans les entrées / sorties. » ; plus aucune référence à `result` ;
  polling `useExecutionPolling` inchangé.
- `.knowledge/memory/maps/map-front-files.json` — 10 entrées (le domaine executions entier
  manquait au registre depuis la phase 7).

Maquette : **aucune modification** — `executions.html` contenait déjà la cible (timeline
Entrée/Sortie + journal) ; le front s'y est aligné (y compris le retour des dots au style
soft de la maquette, cf. Technical Decisions A8).

### Database Changes

Une table : `workflow_execution_logs` (schéma D1). Le JSON `result` de `workflow_executions`
change de **contenu** (résumé sans outputs) mais pas de schéma — les exécutions antérieures à
la phase gardent leur ancien `result` et n'ont pas de logs (données de dev ;
`migrate:fresh --seed` régénère un historique complet). Config : nouvelle section `logs` et
`execution.retention_days` ; aucune entrée scheduler nouvelle (`model:prune` quotidien déjà
en place depuis la phase 1).

### Routes

**Aucune nouvelle route** — les logs transitent par la prop `execution` existante (A7).
`php artisan wayfinder:generate --with-form` exécuté en fin de lot F : aucune signature
d'action ne change, zéro fichier généré modifié.

### Frontend Changes

Points de contrat : prop racine `logs_retention_days` ; prop `execution` = champs de liste +
`input`/`error`/`started_at`/`finished_at` + `logs[]` (**`result` supprimé du contrat**) ;
prop `executions` inchangée. Row de log : `{id, attempt, kind, nodeKey?, nodeType?, nodeName?,
status?, durationMs?, message?, level, input?, output?, error?, offsetMs}`. Garanties de
séquence : 1ʳᵉ row de chaque tentative = event « Exécution démarrée (déclencheur : …) »,
dernière = event final. Le journal n'affiche pas les rows sans message (`queued`/`skipped`)
— seule interprétation possible du contrat, épinglée par test.

### Tests

Backend : **+52 tests** (38 dans 6 nouveaux fichiers + extensions RunWorkflowJobTest +8,
WorkflowExecutionIndexTest +4, ajustements ExecutionCancelTest/ScheduleDispatchTest —
l'assertion `output['cron']` du trigger migre vers sa row de log) — total suite
**679/679, 2 424 assertions**. Front : **+12 specs** — total **127 verts**. PHPStan niveau 7 :
0 erreur. Pint propre. `types:check` OK, `build` OK.

### Commands Executed

- `search-docs` (Boost) : `MassPrunable`/pruning, `upsert`/`updateOrCreate` — avant le code.
- `composer show laravel/framework` (v13.32.0), `make:model --help`, `make:class`,
  `make:enum`, `database-schema` (vérification de l'index unique sur MariaDB).
- Boucles Red → Green par lot (`vendor/bin/pest` périmètre étroit).
- `php artisan migrate` (dev MariaDB), `php artisan model:prune --pretend` (les deux modèles
  détectés), `php artisan wayfinder:generate --with-form`.
- Finaux : `composer test` (Pint + PHPStan + Pest 679/679) ; `npm run test:unit |
  types:check | build`.

## Key Information

### Technical Decisions

Arbitrages A1–A8 appliqués (§0 du plan) ; décisions D1–D8 détaillées dans
`.knowledge/memory/plans/phase8-implementation-plan.md`. Résumé :

- **La table de logs est la source de vérité du parcours** (A1) : une row par node PAR
  tentative (upsert `(execution, attempt, node_key)`) + rows d'événements (`node_key` NULL,
  hors index unique). Le JSON `result` devient un résumé (`toSummaryArray()`, sans
  outputs/inputs) conservé pour l'audit et **retiré de la prop `execution`** — le front ne
  peut plus consommer les payloads non redactés de `result`.
- **Runner pur, adaptations optionnelles** (A2/D3) : `input` dans `NodeRunResult` + hook
  `$onNodeResult` — le même pattern que `$beforeNode` (phase 7) ; aucun test moteur
  préexistant cassé.
- **Seul `RunWorkflowJob` écrit** (A3/D5) : ni le runner ni les contrôleurs ; le test-run
  synchrone ne persiste rien (il ne crée pas d'exécution). Rows `queued` posées à
  `openAttempt` — visibles en polling pendant que le node tourne.
- **Redaction par clés, sur les logs uniquement** (A4/D6) : `execution.input` reste brut en
  base par nécessité fonctionnelle (retries/relance) mais n'est plus servi à l'UI dans le
  détail ; le payload webhook du trigger est redacté dans les logs (test e2e api_key →
  `[masqué]`).
- **Journal stocké, pas recalculé** (A5) : messages FR figés à l'écriture via
  `ExecutionLogMessages` — le front n'invente aucun texte métier ; `offset_ms` est relatif au
  début de **la tentative** (cohérent avec les timings par tentative de la phase 7, D12).
- **Rétention MassPrunable** (A6/D7) : logs 30 j, exécutions **finales** 90 j (`prunable()`
  filtre — une exécution pending/running n'est jamais élaguée) ; `model:prune` quotidien déjà
  planifié ; bornes de taille 2 000 chars/scalar et 64 Ko/colonne (marqueur d'omission).
- **Exposition par la prop existante** (A7/D8) : polling `only:['execution']` inchangé, pas
  de route dédiée ; projection plate camelCase côté contrôleur.
- **Écarts maquette arbitrés** (A8) : « Entrée » = input agrégé du node, séparateurs
  « Tentative N », bloc Erreur au dépliage, caption sans « (plan Pro) », jamais le chemin
  webhook dans « Exécution démarrée », `t` via `formatDurationMs`. Les dots de timeline sont
  revenus au style **soft** de la maquette (le style plein était un écart de la phase 7).

### Gotchas & Solutions

- **MariaDB : identifiants > 64 caractères** (SQLSTATE 1059) sur le nom d'index unique
  conventionnel — nom explicite court `workflow_execution_logs_unique` ; SQLite (tests) ne
  détecte pas ce cas, d'où un écart tests/dev à connaître. `node_key` limité à varchar(64)
  (miroir de `workflow_nodes.key`).
- **`Illuminate\Support\CarbonInterface` n'existe pas** dans ce framework →
  `Carbon\CarbonInterface`.
- **`$callback?($args)`** (nullsafe sur callable) est une **erreur de compilation PHP** —
  closures à instructions dans le runner et le job.
- **Factory `->for($execution)`** exige le nom de la relation : `->for($execution,
  'execution')` + FK `workflow_execution_id` explicite sur le `belongsTo`.
- **Ordre des rows `queued`** = ordre du graphe mappé (positions de l'éditeur), pas l'ordre
  d'insertion — c'est le bon ordre de timeline ; les assertions de tests sont rendues
  insensibles via `keyBy`.
- **Événements hors tentative** (`persistFailed`/`persistCancelled`) : `offset_ms` calculé
  depuis `now()` → bruit sub-ms (sémantiquement 0 ; tests bornés < 100 ms).
- **Événement started écrit AVANT `openAttempt`** : le plan D5 était ambigu sur l'ordre —
  choisi pour que le journal commence naturellement par « Exécution démarrée ».
- **Type-guard front** (`ExecutionTimelineNode`) : encode la garantie « une row node porte
  toujours nodeKey/type/name/status » ; si le back déviait, l'UI afficherait des libellés
  vides plutôt que de crasher.

### Commands & Config

`config/workflows.php` § `logs` : `retention_days` = `WORKFLOW_LOGS_RETENTION_DAYS` (30),
`max_string_chars` = `WORKFLOW_LOGS_MAX_STRING_CHARS` (2 000), `max_json_bytes` =
`WORKFLOW_LOGS_MAX_JSON_BYTES` (65 536), `redacted_keys` = 17 clés exactes (`token`,
`api_key`, `authorization`, `password`, `credentials`…), `redacted_key_suffixes` = 6
(`_token`, `_secret`, `_password`, `_api_key`, `_apikey`, `_credential`).
§ `execution` : + `retention_days` = `WORKFLOW_EXECUTIONS_RETENTION_DAYS` (90). Commandes
utiles : `php artisan model:prune --pretend` (éligibilité), `php artisan migrate:fresh
--seed` (historique de démo avec logs).

### Version Notes

- Laravel 13 : `MassPrunable` n'émet pas d'événements de modèle (élagage en une requête) —
  ne pas compter sur des observers de suppression ; `model:prune` découvre les modèles via
  le trait, aucune déclaration à ajouter.
- L'index unique s'appuie sur le comportement SQL des NULL distincts : les rows d'événements
  (`node_key` NULL) coexistent sans collision — comportement vérifié sur MariaDB ET SQLite.

## Future Ideas (Not Planned)

- **Scan de contenu des secrets** (au-delà des clés) : détecter des valeurs sensibles dans
  des champs anodins — coût/complexité, à arbitrer en phase 11 (durcissement).
- **Masquage/chiffrement de `execution.input`** en base (aujourd'hui brut pour les retries).
- **Durée cumulée multi-tentatives** et horloge absolue du journal (aujourd'hui relatif par
  tentative).
- **Diffusion temps réel** (broadcast) en remplacement/complément du polling 1,5 s —
  candidates phase 10 (notifications in-app) ou phase 13.
- **Filtre/recherche dans le journal, export** (JSON/CSV) d'une exécution.
- **Rétention différenciée par équipe** et métriques/agrégats (dashboard, phase 10).

## Known Limitations

- Redaction **par clés uniquement** : un secret dans une valeur à clé anodine (champ libre
  d'un webhook) n'est pas masqué — pas de scan de contenu.
- `execution.input` stocké brut en base (nécessaire aux retries/relance) ; non rendu dans
  l'UI au détail.
- Chronomètre du journal relatif à chaque tentative ; pas de durée cumulée
  multi-tentatives.
- Exécutions créées avant la phase 8 : pas de logs (données de dev).
- Polling (1,5 s), pas de push temps réel ; pas de filtre/recherche dans le journal ; pas
  d'export.
- Hors scope : enrichissement du tiroir de test (blocs entrée), page de détail par node,
  métriques/agrégats, alerting sur échec, rétention par équipe.
- `npm run check` global échoue sur `docs/reports/phase7/report.md` (formatage préexistant,
  hors périmètre de la phase).

## Next Phase

Phase 9 — Templates : templates système / équipe, duplication, publication, galerie.
