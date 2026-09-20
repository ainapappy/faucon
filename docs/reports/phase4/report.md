# Phase 4 — Workflow Engine

## Summary

La phase 4 introduit le véritable moteur d'exécution Faucon et remplace la simulation client du builder. Côté backend : validation d'exécutabilité (`WorkflowValidator` composant `GraphValidator` inchangé), contrat extensible `NodeHandler` + `NodeHandlerRegistry` rempli explicitement dans `AppServiceProvider`, contexte d'exécution avec interpolation `{{ node.key.chemin }}` par dot-path sécurisée (jamais d'eval, échappement `@{{ }}`), traversal BFS depuis le trigger avec branches `true`/`false`, merge d'inputs, nodes inaccessibles `skipped`, fail-fast et timeout ; cinq handlers initiaux (`trigger.manual`, `data.input`, `data.transform`, `data.output`, `logic.condition`). Endpoint synchrone `POST …/workflows/{workflow}/test-run` (Policy `update`) retournant un `ExecutionResult` sérialisé. Le catalogue passe à **15 types** : `data.output` ajouté (arbitrage utilisateur) et `logic.condition` enrichi de `operator`/`value` (config 3 champs du prompt de phase, maquette alignée). Côté front : modale « Tester » avec input d'échantillon JSON validé en direct, composable `useWorkflowTestRun` (machine `idle → running → completed/failed`, relecture animée des statuts réels), panneau de résultats par node dans le tiroir (statut, durée, output JSON repliable en texte, erreur FR), inspecteur montrant l'output réel ; la simulation client `useWorkflowSimulation` est supprimée. Arbitrages utilisateur actés avant conception : ajout de `data.output` (maquette enrichie d'abord), modale JSON avant tout run, permission `update` (la future `workflow:run` restera pour les exécutions réelles de la phase 7). TDD Pest et Vitest sur chaque lot. Suites : **322 tests Pest, 1111 assertions, 0 échec** (baseline 196) + **36 tests Vitest**. PHPStan niveau 7 : 0 erreur. Build Vite OK. Aucune migration, aucune nouvelle dépendance.

## Implementation

### Files Created

Backend :

- `app/Services/Workflow/NodeHandler.php` — interface unique du moteur : `type(): string` (source du type id), `validate(array $config): array` (messages FR, vide = valide), `execute(NodeContext): NodeResult`.
- `app/Services/Workflow/NodeHandlerRegistry.php` — table `type → handler`, résolue via container, **unique point d'extension du moteur**.
- `app/Services/Workflow/Interpolator.php` — résolution dot-path manuelle (pas `data_get()` : distinguer absent/null, pas de wildcards), `resolve` tolérant / `interpolate` strict, échappement `\{{ … }}`.
- `app/Services/Workflow/ExecutionContext.php` — porte les sorties par node + alias `trigger` ; `value` (placeholder unique → valeur brute) / `interpolate`.
- `app/Services/Workflow/Exception/NodeExecutionException.php` — message utilisateur + détail technique isolé (propriété privée, accès `technicalDetail()`, destiné à `storage/logs`).
- `app/Services/Workflow/WorkflowValidator.php` — exactement un trigger, types connus, handler présent, configs valides selon le schéma catalogue, acyclique (`GraphValidator::hasCycle` réutilisé tel quel) ; retourne une liste d'`ExecutionError` cumulées.
- `app/Services/Workflow/GraphTraverser.php` — successeurs d'un node filtrés par la branche annoncée (`branch null` → tous suivis ; branche annoncée → edges à handle `null` ou égal à la branche), fusion des inputs amont.
- `app/Services/Workflow/WorkflowRunner.php` — orchestre validate → trigger → traversal BFS → handlers, fail-fast, timeout inter-nodes (5 000 ms injectable), nodes non atteints `skipped` (ordre du graphe en fin de liste).
- `app/Services/Workflow/Handlers/Trigger/ManualHandler.php` — passthrough de l'input d'échantillon.
- `app/Services/Workflow/Handlers/Data/InputHandler.php` — expose son input sous nom de variable (`exposeAs`, défaut `payload`, alias `trigger` interdit).
- `app/Services/Workflow/Handlers/Data/TransformHandler.php` — copie de champs (placeholder unique → structure copiée) ou template de texte ; aucun code arbitraire.
- `app/Services/Workflow/Handlers/Data/OutputHandler.php` — node terminal : expose la sortie finale (successeurs `skipped`).
- `app/Services/Workflow/Handlers/Logic/ConditionHandler.php` — `{expression, operator: ==|!=|contains|empty, value}`, branche `true`/`false` portée par `NodeResult->branch`.
- `app/Data/Workflow/{NodeContext,NodeResult,NodeRunResult,ExecutionError,ExecutionResult}.php` — DTOs readonly du moteur (statuts `ok|error|skipped`, `completed|failed`, erreurs `{nodeKey, type, reason, message}`).
- `app/Http/Requests/Workflows/TestRunWorkflowRequest.php` — `input` string nullable ≤ 10 000 chars, `after()` avec `json_decode($raw, true, 10)` (profondeur bornée), messages FR, `sampleInput()` mémoïsé.
- `app/Actions/Workflows/TestRunWorkflow.php` — valide le graphe puis lance `WorkflowRunner` (le moteur ne dépend jamais de la requête HTTP).
- `app/Http/Controllers/Workflows/TestRunWorkflowController.php` — invokable : scoping `$currentTeam->workflows()->whereKey(...)->firstOrFail()`, `Gate::authorize('update', …)`, toujours 200 avec l'`ExecutionResult` sérialisé.
- Tests backend : `tests/Unit/Workflow/{InterpolatorTest(17),NodeHandlerRegistryTest(5),ExecutionContextTest(6),WorkflowValidatorTest(9),GraphTraverserTest(8),WorkflowRunnerTest(11)}.php`, `tests/Unit/Workflow/Handlers/*Test.php` (5 fichiers, table de vérité des 4 opérateurs en datasets), `tests/Feature/Workflows/{WorkflowTestRunTest(14),WorkflowTestRunEndToEndTest(2)}.php`, +3 cas `NodeCatalogTest`, +1 cas `WorkflowGraphValidationTest`.

Frontend :

- `resources/js/composables/useWorkflowTestRun.ts` — machine `idle → running → completed | failed`, couche HTTP injectée, relecture animée des statuts réels (plafonnée ~1,5 s quel que soit le graphe, arêtes « en flux » uniquement sur le chemin exécuté), `parseSampleInput` (validation live miroir du 422 backend), `formatRunSeconds`, `formatNodeOutput` (rendu texte, jamais v-html).
- `resources/js/components/builder/TestRunDialog.vue` — modale fidèle à la maquette : titre « Tester le workflow », textarea JSON mono préremplie une fois, validation live, hint `{{ trigger.* }}` en `v-pre`, bouton « Lancer le test » désactivé si vide/invalide ou requête en vol.
- `resources/js/components/builder/ExecDrawerNodeRow.vue` — ligne de résultat par node : badge statut icône + libellé (En cours/Réussi/Erreur/Non exécuté/En attente), durée, erreur FR, sortie repliable en texte.
- `resources/js/composables/__tests__/useWorkflowTestRun.spec.ts` — 17 tests (timers fake) : transitions, ordre de révélation, arêtes du chemin parcouru, échec → suivants skipped, validation échouée sans relecture, 422 → messages + idle, erreur réseau, double start ignoré, relance/reset, plafond 30 nodes, `resultsByKey`, helpers.

### Files Modified

- `app/Services/Workflow/NodeCatalog.php` — 15ᵉ type `data.output` (libellé « Sortie », icône `arrow-right-to-line`, `input: true`, `outputs: []`, `fields: []`) ; `logic.condition` à 3 champs (`expression`, `operator` select `==|!=|contains|empty`, `value`) — miroir de la maquette enrichie.
- `app/Providers/AppServiceProvider.php` — singleton `NodeHandlerRegistry` construit avec les 5 handlers résolus via `$this->app->make()` (enregistrement explicite, conformément à `domain.md` §2).
- `app/Http/Requests/Workflows/SaveWorkflowGraphRequest.php` — règle générique pilotée par le catalogue : un type à zéro port de sortie ne peut être source d'aucune edge (couvre `data.output`, message EN conformément aux messages existants du request).
- `routes/web.php` — `POST {current_team}/workflows/{workflow}/test-run` nommée `workflows.test-run`, groupe `{current_team}` existant.
- `tests/Unit/Workflow/NodeCatalogTest.php`, `tests/Feature/Workflows/{WorkflowGraphValidationTest,WorkflowCrudTest}.php` — ajustements du contrat (15 types, répartition 3/4/2/3/3, `nodeTypes` props).

Frontend :

- `resources/js/types/workflows.ts` — + `ExecutionStatus`, `ExecutionErrorData`, `NodeRunResult`, `ExecutionResult`, `TestRunState` ; `NodeRunStatus` étendu avec `'skipped'` ; types de simulation supprimés.
- `resources/js/lib/nodeIcons.ts` — + `arrow-right-to-line` (15ᵉ type).
- `resources/js/components/builder/BuilderTopbar.vue` — bouton « Exécuter » → **« Tester »** (fidélité maquette), spinner « En cours… ».
- `resources/js/components/builder/ExecDrawer.vue` — panneau de résultats par node (état vide maquette, badge « En cours », résumé « Succès — N nodes · X.X s » / « Échec — {message} »).
- `resources/js/components/builder/WorkflowNodeCard.vue` — état `skipped` estompé (opacité 0.55).
- `resources/js/components/builder/NodeInspector.vue` — prop `nodeResult` : onglet Sorties affiche l'output réel, durée, erreur, « Node non exécuté pendant le test » si `skipped` ; sans run, aperçu étiqueté inchangé.
- `resources/js/pages/workflows/Edit.vue` — simulation remplacée par `useWorkflowTestRun`, `useHttp<{ input: string }, ExecutionResult>` + `workflows.testRun(...).url`, modale branchée sur le topbar, `onRunStarted` ferme la modale et ouvre le tiroir, toasts succès/échec, `?run=1` **ouvre la modale** (plus d'auto-run silencieux — AM2).
- `resources/js/composables/useWorkflowBuilder.ts` — type `BuilderSimulationHooks` renommé `BuilderRunHooks` (usage confiné).
- Supprimé : `resources/js/composables/useWorkflowSimulation.ts` + sa spec (le run simulé client disparaît, arbitrage U2).
- Commentaires doc mis à jour (`vitest.config.ts`, `Index.vue`, spec builder) ; `.knowledge/memory/maps/map-front-files.json` tenu à jour.

### Database Changes

- **Aucune migration** — le résultat d'exécution est en mémoire et retourné à l'UI (écart assumé avec l'ordre du master §31, justifié dans le prompt de phase) ; persistance `workflow_executions` en phase 7, logs par node en phase 8.
- Aucune variable `.env` ajoutée.

### Routes

| Méthode | URI                                            | Nom                  |
| ------- | ---------------------------------------------- | -------------------- |
| POST    | `{current_team}/workflows/{workflow}/test-run` | `workflows.test-run` |

`php artisan wayfinder:generate --with-form` exécuté : `workflows.testRun({current_team, workflow})` dans `@/routes/workflows` + action typée `@/actions/App/Http/Controllers/Workflows/TestRunWorkflowController` (`.post`/`.form`). Le front n'a aucune URL codée en dur.

### Frontend Changes

- Parcours « Tester » : bouton topbar → modale (input d'échantillon JSON, validation live) → run synchrone → relecture animée des statuts réels → panneau de résultats par node (statut, durée, output JSON repliable en texte, erreur compréhensible) + résumé final. Échec de validation du graphe → résumé « Échec » avec le premier message d'erreur, aucun node relu.
- Machine d'états maître (master §20) : `idle → running → completed | failed`, 422/erreur réseau → retour `idle` avec la modale laissée ouverte et la saisie préservée.
- Statuts jamais portés par la couleur seule : badges icône + libellé (tokens info/success/danger) ; carte `skipped` estompée ; arêtes « en flux » limitées au chemin exécuté (dérivé de la réponse, sans id de type en dur).
- Inspecteur : onglet Sorties affiche l'output réel du node sélectionné après un test ; condition à 3 champs rendue automatiquement depuis le catalogue.
- Palette catégorielle inchangée ; `data.output` hérite du bleu `--cat-2` (Données).

### Tests

- **Pest** : 196 → **322 tests, 1111 assertions, 0 échec** (+126). Interpolation (chemins simples/imbriqués/manquants/tableaux, délimiteurs littéraux, valeurs spéciales), registre, contexte, catalogue (15 types), handlers (validate + execute, table de vérité `==|!=|contains|empty` en datasets), validateur (9 cas dont erreurs cumulées), traverser (BFS, branches, losange, isolé `skipped`), runner (11 cas dont fail-fast, timeout, **preuve d'extensibilité** : handler `test.echo` hors catalogue exécuté sans modifier le moteur), endpoint feature (happy path, 200 `failed` no_trigger/handler_missing, 422 payload — malformé/liste/scalaire/>10 000 chars/profondeur 11, 404/403/401), bout en bout HTTP (save PUT phase 3 → test-run, branches true et false, output terminal).
- **Vitest** : 27 → **36 tests** (−8 simulation supprimée, +17 `useWorkflowTestRun`).
- PHPStan niveau 7 : 0 erreur. Pint : passed après chaque lot. `npm run types:check` et `npm run build` : OK.

### Commands Executed

`php artisan list` + `--help` des générateurs ; `search-docs` (container, collections, exceptions personnalisées) ; `make:class`/`make:interface`/`make:enum` n/a — classes créées via `make:class` et générateurs disponibles ; `make:request`, `make:controller --invokable`, `make:test --pest` ; `php artisan wayfinder:generate --with-form` ; `vendor/bin/pint --dirty --format agent` (fin de chaque lot) ; `vendor/bin/phpstan analyse --no-progress` ; `php artisan test --compact` ; `npm run test:unit` / `types:check` / `check` / `build`.

## Key Information

### Technical Decisions

- **`branch: null` = tous les successeurs suivis** (précision D6) : le builder phase 3 pose `source_handle='out'` sur les edges de ports simples — sans cette règle, aucun graphe sauvegardé depuis le builder ne serait exécutable. Une branche annoncée (`true`/`false`) ne suit que les edges à handle `null` ou égal à la branche.
- **Le registre est l'autorité d'exécutabilité** (précision D8/D9) : un type avec handler au registre est exécutable même hors catalogue (condition du test d'extensibilité `test.echo`) ; un type du catalogue sans handler (`ai.*`, `action.*`, `data.http_request`, `logic.filter`) échoue en validation `handler_missing` — jamais de skip silencieux ; un type hors catalogue reste `unknown_type` (défensif).
- **Interpolation par dot-path manuelle**, pas `data_get()` : distinguer chemin absent de valeur `null`, refuser les wildcards, distinguer `resolve` tolérant (aperçu) d'`interpolate` strict (échec `path_not_found` explicite). Échappement des délimiteurs littéraux. Zéro eval (critère de validation).
- **Contexte** : racine `trigger.*` = sortie du node trigger = input d'échantillon (manuel = passthrough) ; nodes adressables par leur `key` (UUID) ; `data.input` expose une variable nommée ; alias `trigger` interdit aux `data.input`. Nodes exécutés au plus une fois ; input = fusion des sorties amont déjà exécutées (dernier edge gagne sur collision).
- **`data.output` terminal en aval** : ses successeurs sont `skipped`, pas d'abort global — documenté. Nodes inaccessibles = `skipped` (pas une erreur).
- **Rétro-compatibilité des graphes phase 3** : configs tolérées (operator → `==`, value → null, expression vide → passthrough, `name:''` traité comme absent) — l'esprit D12 (graphes existants testables sans réédition).
- **Endpoint toujours 200** avec `ExecutionResult` sérialisé : un workflow qui échoue n'est pas une erreur HTTP, c'est un résultat `failed` (master §26 — node + raison dans la réponse, détail technique en logs). 422 réservé au payload invalide.
- **Modale fermée à la réception de la réponse** (`onRunStarted`), pas au clic « Lancer le test » : une 422/erreur réseau laisse la modale ouverte avec la saisie préservée (master §20, arbitrage vs maquette qui fermait au clic).
- **Relecture animée plafonnée ~1,5 s** : les durées affichées restent celles du serveur ; l'animation est une restitution, pas une reproduction du chronométrage.
- **Permission `update`** pour le test-run (arbitrage utilisateur) — tester fait partie de l'écriture ; `workflow:run` restera pour les exécutions réelles (phase 7).

### Gotchas & Solutions

- **`wayfinder:generate` doit passer avec `--with-form`** (règle phase 3, confirmée) : `workflows.testRun` génère l'export camelCase `testRun`, pas de sous-dossier `test-run/`.
- **Le corps du test-run est une CHAÎNE JSON** dans le champ `input` (`JSON.stringify(sample)`), pas un objet — le backend valide profondeur (10) et taille (10 000 chars) sur la chaîne décodée.
- **2ᵉ générique de `useHttp` type la réponse** (`onSuccess: (response: ExecutionResult)`) ; `RequestFailure` (classe de `useWorkflowSaver`) réutilisée pour transporter les messages 422/réseau.
- **Apostrophe typographique (’) obligatoire** dans les chaînes JS single-quoted (un sed avec apostrophe droite a cassé la spec builder, corrigé et re-testé).
- **PHP 8.4** : toujours pas de `new` dans les constantes/initializers statiques (définitions catalogue memoizées, cf. phase 3) ; `NodeExecutionException` porte son détail technique en propriété privée readonly + accesseur.
- **`after(): array`** (pas `withValidator()`) pour les règles croisées du request test-run ; `json_decode($raw, true, 10)` borne la profondeur au décodage.
- **`.field-error` de la maquette rendue en utilitaires tokens** (`text-destructive text-xs`) : `InputError.vue` code `text-red-600` hors tokens — à homogénéiser le cas échéant.

### Commands & Config

- Tests front : `npm run test:unit` (Vitest, `resources/js/vitest.config.ts`, specs `composables/__tests__/`).
- Génération routes : `php artisan wayfinder:generate --with-form` (règle projet).
- Aucune nouvelle variable `.env`, aucune valeur de config : timeout du runner injectable (défaut 5 000 ms), bornes du request en constantes de classe.

### Version Notes

- Inertia `@inertiajs/vue3` 3.7.1 : `useHttp<S, R>` — le 2ᵉ générique type `onSuccess` ; le corps part de l'état réactif du form (comme `useForm`).
- Laravel 13 / Pest 3 : `after(): array` dans les Form Requests ; datasets Pest pour la table de vérité des opérateurs.
- PHP 8.4 / Larastan niveau 7 : DTOs readonly + types explicites passent sans friction ; propriété privée readonly + accesseur pour le détail technique de l'exception.

## Future Ideas (Not Planned)

- **Edges d'erreur (on-fail)** : une branche « erreur » par node permettrait des graphes résilients (fallback) sans stopper le run. À évaluer en phase 7 avec les retries/cancellations (même famille de traversal).
- **Boucles et itération sur listes** : le BFS actuel exécute chaque node au plus une fois ; une sémantique de boucle (forEach sur un tableau du contexte) changerait le modèle de traversal. À évaluer quand un cas d'usage réel l'exigera (post-phase 7).
- **Exécution partielle depuis un node donné** : rejouer « à partir d'ici » avec le contexte figé accélérerait l'itération sur un sous-graphe. À évaluer en phase 7/8 avec la persistance des exécutions.
- **Parallélisation des branches** : les branches indépendantes pourraient s'exécuter en parallèle (futures). Inutile en synchrone ; pertinent en phase 7 (queues).
- **Expressions enrichies** (filtres, formatage de dates/nombres dans les templates) : l'interpolation dot-path volontairement minimale ; toute expression doit rester déclarative et non-évaluable. À évaluer sur retours d'usage (phase 10+).
- **Debug pas-à-pas (breakpoints)** : la relecture animée en est l'embryon côté UI ; un vrai pas-à-pas exigera un moteur en queue avec états persistés (phase 7+).
- **Capture de l'input par node** : l'onglet Entrées de l'inspecteur garde un aperçu étiqueté car la réponse ne contient pas l'input de chaque node ; ajouter `input` à `NodeRunResult` enrichirait le debug. À évaluer phase 8 (logs par node).
- **Lien « Voir l'exécution complète »** du résumé (maquette) omis : l'écran executions arrive en phase 7 — l'ajouter alors.

## Known Limitations

- Exécution **synchrone en mémoire** : pas de persistance, pas de queue, pas de retry/cancellation (phase 7) ; timeout inter-nodes 5 000 ms côté moteur.
- Seuls 5 types sont exécutables (`trigger.manual`, `data.input`, `data.transform`, `data.output`, `logic.condition`) ; les autres (`ai.*`, `action.*`, `data.http_request`, `logic.filter`) échouent en validation `handler_missing` avec message explicite (phases 5-6 les livreront).
- Le test-run n'exécute que les types dotés d'un handler : un graphe dont le trigger est `webhook`/`schedule` échoue en validation `handler_missing` sur le node trigger (le registre fait autorité) — cohérent avec le périmètre « Tester depuis l'UI ».
- La relecture animée plafonne à ~1,5 s ; l'onglet Entrées de l'inspecteur garde un aperçu étiqueté (pas d'input par node dans la réponse) ; le lien « Voir l'exécution complète » est omis (écran phase 7).
- Vérification interactive navigateur du parcours complet (créer → connecter → Tester avec échantillon → branches → activer) à jouer manuellement — validée au niveau contrat (suites vertes, contrat JSON testé en feature).

## Next Phase

**Phase 5 — Actions & Integrations** : handlers `action.email`/`action.message`/`action.delay` et `data.http_request` (client HTTP durci SSRF/timeout, `App\Services\Integration\*`), abstractions d'intégration, secrets management (master §30) — le moteur n'a pas à être modifié pour les accueillir : un handler + un enregistrement au registre par type, conformément au contrat d'extension prouvé cette phase.
