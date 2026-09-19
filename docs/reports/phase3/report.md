# Phase 3 — Workflow Builder

## Summary

Implémentation de bout en bout du Workflow Builder, cœur du domaine Faucon : schéma de données du graphe (`workflows` / `workflow_nodes` / `workflow_edges`, team-scoped, soft deletes), modèles + factories + enum `WorkflowStatus` (deux états, conformément à `domain.md` §4.3 — écart assumé au prompt de phase qui mentionnait `archived`), catalogue des 14 types de nodes (`NodeCatalog`, format `category.type`, exposé au front en props), `GraphValidator` (détection de cycle par DFS), `WorkflowPolicy` sur le gabarit d'autorisation phase 2 + permissions `workflow:create|update|delete`, CRUD complet + duplication + sauvegarde transactionnelle du graphe (`PUT …/graph`, validation stricte : edges orphelins, handles par type, doublons, whitelist de config, bornes). Côté front : page liste fidèle à `workflows.html` (tous les éléments conservés à la demande de l'utilisateur), builder complet fidèle à `builder.html` (`@vue-flow/core` : palette, canvas, inspecteur, pan/zoom, run simulé client), logique métier dans des composables testés (Vitest). Décisions d'amorçage validées avec l'utilisateur avant l'implémentation (run simulé inclus, écarts de liste conservés). TDD Pest et Vitest sur chaque lot. Suite : baseline 122 tests / 428 assertions → **191 tests, 728 assertions, 0 échec** + **27 tests Vitest**.

## Implementation

### Files Created

Backend :

- `database/migrations/2026_09_19_091355_create_workflow_tables.php` — une migration groupée (précédent : `create_teams_table`) : `workflows` (`team_id` FK cascade posée après `id`, `created_by` FK nullable, `name`, `description`, `status` default `draft`, softDeletes, index `(team_id, status)`), `workflow_nodes` (`workflow_id` FK cascade, `key`, `type`, `name`, `config` JSON, `position_x/y`, unique `(workflow_id, key)`), `workflow_edges` (`workflow_id` FK cascade, `source_node_key`, `target_node_key`, `source_handle` nullable, index `(workflow_id)`). Pas de `team_id` dénormalisé, pas de FK edges→nodes (intégrité par validation).
- `app/Models/Workflow.php` — fillable minimal, casts `status` → enum, `SoftDeletes` ; relations `team`, `creator`, `nodes`, `edges`, `triggerNode` (HasOne contraint sur les types trigger **résolus via `NodeCatalog`**, pas de `LIKE 'trigger.%'`).
- `app/Models/WorkflowNode.php`, `app/Models/WorkflowEdge.php` — casts `config` array, positions integer.
- `app/Enums/WorkflowStatus.php` — `Draft`/`Active` (deux états seulement). `app/Enums/NodeCategory.php` — `Trigger/Data/Logic/Ai/Action` (segment `Ai`, pas `AI`).
- `app/Services/Workflow/NodeCatalog.php` — source de vérité unique des 14 types (`trigger.webhook`, `trigger.schedule`, `trigger.manual`, `data.input`, `data.transform`, `data.http_request`, `logic.condition`, `logic.filter`, `ai.classification`, `ai.generation`, `ai.summary`, `action.email`, `action.message`, `action.delay`) avec métadonnées d'édition (libellé FR, description de la maquette, icône lucide, ports, schéma de champs).
- `app/Data/Workflow/NodeDefinition.php` — DTO readonly sérialisé tel quel en props Inertia.
- `app/Services/Workflow/GraphValidator.php` — `hasCycle()` DFS trois couleurs (né en phase 3, étendu phase 4 — conformément à `domain.md` §2).
- `app/Policies/WorkflowPolicy.php` — gabarit `authorization.md` à la lettre : `create(User, Team)` / `viewAny` / `view` / `update` / `delete` / `duplicate` (= permission create sur l'équipe).
- `app/Http/Requests/Workflows/{CreateWorkflowRequest,UpdateWorkflowRequest,SaveWorkflowGraphRequest}.php` — règles de forme (bornes 100 nodes / 200 edges, positions ±100 000, config scalaires ≤ 10 000 chars) + règles croisées en `after()` (edges référencent des clés existantes, `sourceHandle` ∈ sorties du type, triplet unique, whitelist de config par catalogue, cycle).
- `app/Http/Controllers/Workflows/WorkflowController.php` (index/store/edit/update/destroy/duplicate) et `SaveWorkflowGraphController.php` (invokable) — minces : autorisation Policy, résolution scoped `$currentTeam->workflows()->whereKey(...)->firstOrFail()` (`{workflow}` scalaire, jamais d'ID global), actions, mapping props camelCase.
- `app/Actions/Workflows/{CreateWorkflow,SaveWorkflowGraph,DuplicateWorkflow}.php` — la sauvegarde remplace edges + nodes dans une `DB::transaction` ; la duplication copie graphe complet (mêmes clés, unicité par workflow) en brouillon « (copie) ».
- `database/factories/{WorkflowFactory,WorkflowNodeFactory,WorkflowEdgeFactory}.php` — états `active()`, `trashed()`, `ofType()`, `withConfig()`, `at()`, `keyed()`, `between()`.
- Tests backend : `tests/Feature/Workflows/{WorkflowModelTest,WorkflowPolicyTest,WorkflowGraphValidationTest,WorkflowCrudTest,WorkflowGraphTest,WorkflowAuthorizationTest}.php`, `tests/Unit/Workflow/{NodeCatalogTest,GraphValidatorTest}.php` — 69 tests, TDD Red→Green par lot.

Frontend :

- `resources/js/types/workflows.ts` — `WorkflowStatus`, `NodeCategory`, `NodePort`, `NodeField`, `NodeTypeDefinition`, `WorkflowNodeData`, `WorkflowEdgeData`, `WorkflowGraph`, `SaveState`, `NodeRunStatus` (barrel `types/index.ts`).
- `resources/js/lib/nodeCategories.ts` — présentation des 5 catégories (libellé FR, token `--cat-1..5`, icône, ordre maquette) ; seules ids littérales autorisées côté front. `resources/js/lib/nodeIcons.ts` — nom d'icône catalogue → lucide, fallback muet.
- `resources/js/components/ui/{table,tabs,switch,textarea}/` — composants officiels shadcn-vue (reka-ui) ajoutés au kit, style du registre (18 fichiers), kit existant intact.
- `resources/js/composables/useWorkflowBuilder.ts` — modèle de graphe pur (testable hors navigateur), pont vers `useVueFlow('workflow-builder')` par id partagé : `addNode` (clé UUID, config initialisée du schéma), `removeNode` cascade edges, `connect` (refus self-loop/doublon), `moveNode` (arrondi entier au seul endroit autorisé), `toGraphPayload`.
- `resources/js/composables/useWorkflowSaver.ts` — machine `idle → saving → saved | error`, PUT graph debouncé (~800 ms), re-planification si changement pendant un PUT en vol (baseline figée sur le payload **envoyé**), `saveMetadata` (PATCH), `flush()`, garde `beforeunload`.
- `resources/js/composables/useWorkflowSimulation.ts` — run simulé client (décision utilisateur A1) : portage de `builder.js` — ordre topologique BFS, statuts `idle/running/ok/error`, durées aléatoires, logs horodatés (mention « simulation locale, aucune donnée réelle »), animation des edges, résumé, graphe vide géré.
- `resources/js/composables/useWorkflowList.ts` — recherche/filtre/tri client de la liste.
- `resources/js/pages/workflows/Index.vue` — liste conforme `workflows.html` avec **tous** les éléments (A2) : recherche, chips Tous/Actifs/En pause, tri Plus récents/Nom (A→Z)/Exécutions, bascule grille/liste, cartes + tableau (colonnes « Dernière exécution »/« Succès » à « — »), switch Actif/En pause optimiste (PATCH, 422 → toast), menu contextuel complet (Ouvrir l'éditeur / Exécuter maintenant → `?run=1` / Dupliquer / Supprimer), bouton Importer (toast informatif), dialog de création avec select template statique + mention d'aide, empty state, gating par `permissions`.
- `resources/js/components/workflows/{WorkflowCard,WorkflowTable,WorkflowToolbar,CreateWorkflowDialog}.vue`.
- `resources/js/pages/workflows/Edit.vue` — builder plein écran (layout null) : assemblage topbar + palette + canvas + inspecteur + tiroir, auto-run si `?run=1`.
- `resources/js/components/builder/{BuilderTopbar,NodePalette,BuilderCanvas,WorkflowNodeCard,NodeInspector,ExecDrawer}.vue` — fidélité `builder.html` : nodes 190×78, ports positionnés (branches `true`/`false` de `logic.condition`), beziers + labels, palette groupée par catégorie, inspecteur à onglets (Paramètres + Entrées/Sorties « aperçu simulé »), tiroir journal, grille 22 px (`@vue-flow/background`), styles 100 % tokens (jamais `theme-default.css`).
- `resources/js/composables/__tests__/{useWorkflowBuilder,useWorkflowSaver,useWorkflowSimulation}.spec.ts` + `resources/js/vitest.config.ts` — 27 tests (premiers tests front du projet ; convention `__tests__/` posée).

### Files Modified

- `app/Enums/TeamPermission.php` — + `WorkflowCreate/WorkflowUpdate/WorkflowDelete` (`workflow:create|update|delete`).
- `app/Enums/TeamRole.php` — Admin += les trois ; Member += Create + Update (collaboratif ; destructive réservée Owner/Admin).
- `app/Data/TeamPermissions.php` + `app/Concerns/HasTeams.php` — + `canCreateWorkflow/canUpdateWorkflow/canDeleteWorkflow`.
- `app/Models/Team.php` — relation `workflows(): HasMany<Workflow>`.
- `routes/web.php` — 7 routes nommées dans le groupe `{current_team}` existant.
- `tests/Pest.php` — binding Pest pour `tests/Unit` (sans RefreshDatabase) + helpers `teamWithMember()` et `graphPayload()`.
- `resources/views/app.blade.php` — entrée Vite de page conditionnée à `file_exists` du .vue (levait une ViteException de test tant que les pages n'existaient pas ; préchargement restauré automatiquement depuis leur création).
- `resources/js/app.ts` — `workflows/Edit` → layout null. `resources/js/components/AppSidebar.vue` — nav item « Workflows » (pattern `dashboardUrl`). `resources/js/types/{index,teams}.ts` — barrel + permissions.
- `tests/Feature/Workflows/WorkflowCrudTest.php` — assertions `component('workflows/Index')` durcies (contournement `false` levé une fois les pages créées).
- `package.json` / `package-lock.json` — `@vue-flow/core@1.48.2`, `@vue-flow/background@1.3.2` (décision D3), `vitest@5.0.1` devDependency + script `test:unit`.
- `vite.config.ts` — chargement police bunny JetBrains Mono ; `resources/css/app.css` — token `--font-mono` (miroir de `tokens.css`, règle de synchronisation du design system) ; `tsconfig.json` — specs Vitest exclues.

### Database Changes

- Une migration (`create_workflow_tables`), appliquée en dev (MariaDB, up → rollback → up vérifié). Schéma de tests : sqlite `:memory:` via RefreshDatabase.
- Aucune variable `.env` ajoutée.

### Routes

`{current_team}` = préfixe slug + `EnsureTeamMembership` (groupe existant) :

| Méthode | URI                                             | Nom                      |
| ------- | ----------------------------------------------- | ------------------------ |
| GET     | `{current_team}/workflows`                      | `workflows.index`        |
| POST    | `{current_team}/workflows`                      | `workflows.store`        |
| GET     | `{current_team}/workflows/{workflow}/edit`      | `workflows.edit`         |
| PATCH   | `{current_team}/workflows/{workflow}`           | `workflows.update`       |
| DELETE  | `{current_team}/workflows/{workflow}`           | `workflows.destroy`      |
| POST    | `{current_team}/workflows/{workflow}/duplicate` | `workflows.duplicate`    |
| PUT     | `{current_team}/workflows/{workflow}/graph`     | `workflows.graph.update` |

`php artisan wayfinder:generate` exécuté (avec `--with-form`, cf. Gotchas) : `@/routes/workflows` (+ sous-module `graph`) et actions typées `@/actions/App/Http/Controllers/Workflows/*`. Le front n'a aucune URL codée en dur.

### Frontend Changes

- Deux pages Inertia (`workflows/Index` sur `AppLayout`, `workflows/Edit` plein écran), 10 composants dédiés (4 liste + 6 builder), 4 composants de kit ajoutés, 4 composables + 1 composable de liste, 2 lib (catégories, icônes), types TS complets.
- Palette catégorielle respectée : Déclencheurs ambre `--cat-1`, Données bleu `--cat-2`, IA fuchsia `--cat-3`, Actions émeraude `--cat-4`, Logique violet `--cat-5` — jamais la couleur seule (icône + libellé systématiques).
- Le catalogue de types servi en props est la seule source des types affichés (palette, inspecteur, cartes) : aucune dérive front/back possible.

### Tests

- **Pest** : 122 → **191 tests, 728 assertions**, 0 échec. Modèle/cascade (soft delete conserve, forceDelete cascade), policy (matrice de rôles, non-membre, `duplicate`), catalogue (14 types, format des ids, répartition 3/3/2/3/3, ports de `logic.condition`), `GraphValidator` (chaîne, losange, composantes déconnectées, cycles 2/3 nodes, self-loop), validation graphe (13 cas 422 : edge orphelin citant la clé, type inconnu, doublon, cycle, bornes, config non déclarée/non scalaire, handle invalide, clés dupliquées), CRUD + scoping (exclusion des autres équipes, 404 cross-team, 403 middleware, 401 invité JSON), sauvegarde (save initial, re-save sans doublons, 204), activation (422 sans trigger, message sur `status`).
- **Vitest** : **27 tests** (saver : transitions d'état, debounce = 1 requête, re-planification pendant PUT, erreur → `error` ; builder : round-trip chargement→payload, refus self-loop/doublon, cascade de suppression ; simulation : ordre topologique sur graphe en losange).
- PHPStan (Larastan) niveau 7 : 0 erreur. Pint : passed.

### Commands Executed

`php artisan list` + `--help` des générateurs ; `make:migration`, `make:model -f` ×3, `make:enum` ×2, `make:class` ×6, `make:policy`, `make:request` ×3, `make:controller` ×2, `make:test --pest` ×8 ; `migrate` / `migrate:rollback` / `migrate` (dev) ; `php artisan wayfinder:generate --with-form` ; `vendor/bin/pint --dirty --format agent` (fin de chaque lot) ; `vendor/bin/phpstan analyse --no-progress` ; `php artisan test --compact` ; `npm i @vue-flow/core @vue-flow/background` puis `npm i -D --legacy-peer-deps vitest` ; `npm run check` / `types:check` / `test:unit` / `build`.

## Key Information

### Technical Decisions

- **Deux états `draft | active` (rejet de `archived`)** : `domain.md` §4.3 est liant (tout 3ᵉ état exige une revue d'architecture) ; le besoin est couvert par le soft delete. Écart documenté au prompt de phase (qui mentionnait `archived`).
- **Éditeur `@vue-flow/core` + `@vue-flow/background`** (master §23) : pan/zoom centré curseur, hit-testing des connexions, ports multiples, touch, fitView = matière à bugs subtils ; réutilisable phases 4+ (statuts par node, edges animés, handles) ; ~100 Ko sur route lazy ; style 100 % custom via tokens. Alternative écartée : canvas SVG maison (portage de la maquette = maintenance à vie, aucun support tactile).
- **Sauvegarde `PUT …/graph` en remplacement transactionnel** (delete + réinsertion), payload camelCase, clés UUID générées côté client ; 204 en succès, 422 JSON avec erreurs indexées. Nom/statut passent par un `PATCH` séparé (une responsabilité par endpoint ; la liste réutilise le PATCH pour le toggle).
- **Liste sans pagination** (décision architecte) : `withCount('nodes')` + `triggerNode` = 2 requêtes, zéro N+1, ~250 o/ligne ; recherche/filtre/tri client comme la maquette. Seuil de revisite : > 200 workflows ou payload > 150 Ko (phase 13).
- **Permissions** : Admin administre le domaine (créer/éditer/supprimer), Member collabore (créer/éditer) mais ne détruit pas ; `workflow:run` attendra le moteur (phase 4/7).
- **Run simulé 100 % client** et **tous les éléments de maquette conservés sur la liste** (Importer toast, Dupliquer, Exécuter maintenant, select template statique, colonnes « — », libellés « En pause ») : choix explicites de l'utilisateur, au-delà du strict phasage — à refondre en phase 4 (run réel) et phase 9 (templates).
- **Icône de déclencheur colorée par catégorie** (ambre) et non par type : la maquette contredisait sa propre palette catégorielle liante (`index.html`) ; la règle CVD (couleur jamais seule) prime, écart documenté.
- **Catalogue en service sans état, pas d'enum de types** (`domain.md` §3) ; `NodeCategory` seul enum (catégories fixées). Le `triggerNode` de `Workflow` résout via le catalogue, pas via un préfixe SQL.

### Gotchas & Solutions

- **PHP 8.4 refuse `new` dans les constantes de classe et les initializers de propriétés statiques** : les définitions du catalogue vivent dans une propriété statique privée memoizée (`definitions()`), commentée dans le code.
- **`required` échoue sur les tableaux vides** : le graphe vide étant un état légitime, `nodes`/`edges` utilisent `present` (les deux clés doivent être envoyées, peuvent être `[]`).
- **`withValidator()` n'existe plus en Laravel 13** : les règles croisées utilisent la méthode `after(): array` des Form Requests.
- **Invité sur endpoint JSON → 401** (et non redirect login) : comportement standard Laravel quand `expectsJson` ; le redirect reste testé sur le GET navigateur.
- **ViteException en tests tant que les pages .vue n'existaient pas** : entrée Vite de `app.blade.php` conditionnée à `file_exists` — pont temporaire devenu transparent après création des pages.
- **Fichiers Wayfinder générés périmètres / sans variantes `.form` faisaient échouer `types:check` sur ~15 fichiers préexistants** : la génération doit passer par **`php artisan wayfinder:generate --with-form`** (équivalent du plugin Vite `formVariants: true`). Règle projet à retenir.
- **`useHttp` est data-bound comme `useForm`** : le corps part de l'état réactif du form, pas d'un argument. Vérifié au passage : Inertia 3.7.1 envoie `Accept: application/json` par défaut — les 422 arrivent bien en JSON.
- **Baseline de synchronisation figée sur le payload envoyé** (pas l'état courant) : sinon une édition pendant un PUT en vol serait marquée « synchronisé » et perdue — testé.
- **`npm i -D vitest` échoue en ERESOLVE** (conflit peer `@vitest/browser-preview` côté registre, sans rapport avec l'arbre du projet) : installation avec `--legacy-peer-deps`, lock résolu proprement (un `npm ci` frais n'a pas besoin du flag).

### Commands & Config

- Tests front : `npm run test:unit` (Vitest, config `resources/js/vitest.config.ts`).
- Génération des routes : `php artisan wayfinder:generate --with-form`.
- Aucune nouvelle variable `.env`, aucune valeur de config ajoutée ; le builder est servy par les props (`nodeTypes`, `graph`, `permissions`).

### Version Notes

- `@vue-flow/core` 1.48.2, `@vue-flow/background` 1.3.2, `vitest` 5.0.1 (nouvelles devDeps ; seules additions).
- Inertia `@inertiajs/vue3` 3.7.1 : `useHttp` envoie `Accept: application/json` par défaut ; `assertInertia` v3 vérifie l'existence du fichier composant (d'où les `component(name, false)` temporaires).
- Laravel 13 : `after(): array` remplace `withValidator()` dans les Form Requests ; `Rule::enum()` utilisé pour le statut.
- PHP 8.4 : pas de `new` dans les constantes/initializers statiques.

## Future Ideas (Not Planned)

- **Versioning et rollback des workflows** (brouillon vs version publiée) : la sauvegarde en remplacement rend l'historique impossible ; à coupler au snapshot d'exécution tranché en phase 7. À évaluer phase 7+.
- **Import/export JSON d'un workflow** : le bouton Importer est un toast informatif ; un vrai import réutiliserait la validation du graphe (`SaveWorkflowGraphRequest`) côté serveur. À évaluer phase 9 (avec les templates, qui sont une forme d'export natif).
- **Undo/redo dans l'éditeur** : la stack du graphe (commands inversables) est facile à poser tant que les actions passent par le composable unique. À évaluer après les premiers retours d'usage du builder (phase 10).
- **Collaboration temps réel** (présence, curseurs) : hors monolithe Inertia sans websockets ; à réévaluer seulement si le produit le justifie (phase 10+).
- **Duplication d'un node et sous-graphes réutilisables** : la duplication de workflow existe (`DuplicateWorkflow`) ; dupliquer un node est trivial (nouvelle clé, mêmes configs) et pourrait accompagner le menu contextuel du canvas en phase 4+.
- **Confirmation avant suppression** : la maquette supprime sans confirm (soft delete en filet) ; un dialog de confirmation serait cohérent avec les autres modales destructives du projet. À trancher lors d'une passe UX (phase 10).
- **Pagination serveur de la liste** : seuil défini (> 200 workflows ou payload > 150 Ko), travail désigné phase 13 avec la recherche serveur.

## Known Limitations

- Le run est une **simulation purement client** (aucun état backend, journal explicite « simulation locale ») : le parcours réel « Tester » arrive en phase 4.
- L'activation exige seulement ≥ 1 node trigger ; la validation complète du graphe (champs requis par type, exactement un trigger, exécutabilité) est le travail de `GraphValidator` en phase 4.
- Le tri « Exécutions » et les colonnes « Dernière exécution »/« Succès » sont inertes (« — ») tant qu'aucune exécution réelle n'existe (phases 4-7).
- Le select « Partir d'un template » du dialog de création est statique (templates réels : phase 9).
- Liste sans pagination (seuil de revisite documenté) ; suppression sans dialog de confirmation (comme la maquette, soft delete en filet).
- Vérification interactive navigateur du parcours complet (créer → connecter → autosave → Exécuter → recharger → activer → dupliquer → supprimer) à jouer manuellement — validée au niveau contrat (props, payloads, routes, suites vertes).

## Next Phase

**Phase 4 — Workflow Engine** : validation complète (`GraphValidator` étendu : exactement un trigger, champs requis par type), registre de handlers (`NodeHandlerRegistry` rempli dans `AppServiceProvider`), `ExecutionContext` (variables `{{ trigger.email }}`…), `GraphTraverser` (branches true/false), `WorkflowRunner`, et remplacement du run simulé par un vrai test run (maquette `builder.html`, parcours « Tester » ; maquette `executions.html` pour le suivi). Permissions `workflow:run` à introduire.
