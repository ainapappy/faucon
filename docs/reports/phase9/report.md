# Phase 9 — Templates

## Summary

La phase accélère la création de workflows : table `workflow_templates` (snapshot JSON du
graphe, origine `system|team`), service `WorkflowTemplater` (`instantiate`, `duplicate`,
`publish`), Policy `WorkflowTemplatePolicy`, routes de galerie/utilisation/publication et
seeder de **3 templates système validés** (chacun passe le `WorkflowValidator` dans un test).
Le snapshot réutilise **exactement** le payload Phase 3 (`WorkflowGraphMapper::snapshot` /
`fromSnapshot`) — aucun nouveau format de sérialisation. La **duplication existante** (phase 3)
est déplacée de l'action `DuplicateWorkflow` (supprimée) vers `WorkflowTemplater::duplicate`,
`WorkflowCrudTest` restant vert sans changer d'assertion. Front : maquette `templates.html`
**corrigée d'abord** (sections « Système » / « Mon équipe », badge d'origine, métriques
réelles « N nodes » + catégorie, vedette « Recommandé » — arbitrages validés par
l'utilisateur), puis galerie `templates/Index` fidèle (chips origine/catégorie avec
compteurs, carte vedette, 3 empty states, processing), **aperçu de graphe en SVG statique**
(couche pure testée Vitest), confirmation de duplication, modal « Publier comme template »
à deux points d'entrée (menu de carte + éditeur avec `saver.flush()` préalable), lien
« Parcourir les templates » dans le dialogue de création, entrée sidebar. La chaîne
**publier → utiliser → exécuter** est prouvée par un test e2e (instanciation du template
webhook → validator vide → exécution completed, mail envoyé). Suites finales : Pest
**726/726** (2 709 assertions, +47), Vitest **138/138** (+11), PHPStan niveau 7 **0 erreur**,
Pint propre, `build` et `types:check` OK, `migrate:fresh --seed` produit une base de démo
complète (templates inclus).

## Implementation

### Files Created

Backend :

- `database/migrations/2026_09_20_135724_create_workflow_templates_table.php` — `team_id`
  nullable FK cascade (null = système), `created_by` nullable FK users nullOnDelete, `name`,
  `description` nullable, `category`, `origin` (default `team`), `graph` JSON, timestamps ;
  index `category`, `origin`, `(team_id, category)`.
- `app/Models/WorkflowTemplate.php` — `#[Fillable]` (team_id/created_by hors fillable),
  casts enum + array, relations `team()`/`creator()`, scope `scopeVisibleFor` (OR
  parenthésé par closure, combinable avec `whereKey`).
- `app/Enums/TemplateOrigin.php` — `System|Team`, `label()` FR, `isSystem()`.
- `database/factories/WorkflowTemplateFactory.php` — graphe minimal valide en constante,
  states `system()`/`team()`/`withGraph()` (défaut = équipe).
- `app/Services/Workflow/WorkflowTemplater.php` — `instantiate` (draft, nodes/edges
  fidèles sans re-keying, transactionnel, `EnsureWebhookEndpoint` après transaction),
  `duplicate` (« (copie) », original jamais muté), `publish` (validation préalable
  `WorkflowValidator`, snapshot via mapper, republication = nouveau template).
- `app/Services/Workflow/Exception/TemplateNotPublishableException.php` — porte les
  `ExecutionError` + `firstMessage()`.
- `app/Policies/WorkflowTemplatePolicy.php` — `viewAny`, `view` (system → tout
  authentifié ; team → équipe propriétaire), `use` = view + permission de créer un
  workflow dans l'équipe (arbitrage validé), `delete` (définie, non routée).
- `app/Http/Controllers/Templates/WorkflowTemplateController.php` — galerie : projection
  `{id, name, description, category, origin, nodesCount, graph}` + props `nodeTypes` et
  `permissions`.
- `app/Http/Controllers/Templates/UseWorkflowTemplateController.php` — invokable :
  résolution `visibleFor + firstOrFail` (jamais de find global), `Gate::authorize('use',
[$template, $team])`, instanciation, flash toast, redirect éditeur.
- `app/Http/Controllers/Workflows/PublishWorkflowTemplateController.php` — invokable :
  scoping `$currentTeam->workflows()`, Gate `publish`, catch
  `TemplateNotPublishableException` → `back()->withErrors(['graph' => …])`, succès →
  `back()` sans flash (toast géré par la modal front).
- `app/Http/Requests/Workflows/PublishWorkflowTemplateRequest.php` — name required
  max:255, description nullable max:1000, category required max:60 (libre, pas d'enum).
- `database/seeders/TemplateSeeder.php` — 3 templates système (Support client prioritaire ;
  Traitement de leads (IA) ; Veille du matin), configs alignées sur les handlers réels,
  idempotent `firstOrCreate`.
- Tests (7 fichiers, 47 tests) : `tests/Feature/Workflows/WorkflowTemplateModelTest.php`
  (10), `tests/Unit/Workflow/WorkflowTemplaterTest.php` (12),
  `tests/Feature/Workflows/WorkflowTemplateAuthorizationTest.php` (6),
  `tests/Feature/Workflows/WorkflowTemplateUseTest.php` (6, dont e2e exécution),
  `tests/Feature/Workflows/WorkflowTemplatePublishTest.php` (5),
  `tests/Feature/Workflows/WorkflowTemplateGalleryTest.php` (4),
  `tests/Feature/TemplateSeederTest.php` (4).

Frontend :

- `resources/js/types/templates.ts` — `TemplateOrigin`, `TemplateGraph` (= payload Phase 3
  réutilisé), `WorkflowTemplateListItem` (miroir de la projection contrôleur).
- `resources/js/lib/templatePreview.ts` — pur : `buildPreviewLayout` (normalisation bbox,
  hauteur par rangées, plancher 64), `previewEdgePath` (Bézier dx planché, arrondi 2
  décimales), `previewLabel` (troncature), couleurs via `categoryPresentation`.
- `resources/js/composables/useTemplateGallery.ts` — filtres origine/catégorie avec
  compteurs dérivés, sections, vedette, état processing.
- `resources/js/components/templates/TemplateGraphPreview.vue` — SVG statique déclaratif
  (aucun `v-html`), non interactif, tokens clair/sombre.
- `resources/js/components/templates/TemplateCard.vue` — badge origine + « Recommandé »,
  méta réelles, gating `canCreateWorkflow`, spinner sur la carte active.
- `resources/js/components/templates/TemplateFilterChips.vue` — rangée de chips réutilisée.
- `resources/js/pages/templates/Index.vue` — galerie fidèle à la maquette corrigée.
- `resources/js/components/workflows/DuplicateWorkflowDialog.vue` — confirmation.
- `resources/js/components/workflows/PublishTemplateDialog.vue` — nom/description
  préremplis, catégorie libre + datalist, `useForm` + wayfinder, toast local avec action
  « Voir la galerie », `errors.graph` en alerte destructive.
- `resources/js/lib/__tests__/templatePreview.spec.ts` — 11 specs.

### Files Modified

- `app/Services/Workflow/WorkflowGraphMapper.php` — + `snapshot(Workflow): array` et
  `fromSnapshot(array): array` (statique) ; `map()` inchangé.
- `app/Policies/WorkflowPolicy.php` — + `publish()` (= `update`).
- `app/Http/Controllers/Workflows/WorkflowController.php` — duplicate délégué au service ;
    - prop `templateCategories` (catégories distinctes des templates visibles).
- `routes/web.php` — + 3 routes (groupe `{current_team}`).
- `database/seeders/DatabaseSeeder.php` — + `TemplateSeeder`.
- `app/Actions/Workflows/DuplicateWorkflow.php` — **supprimé** (logique déplacée dans le
  service ; contrôleur et test inchangés).
- Front : `AppSidebar.vue` (entrée « Templates », icône Layers), `WorkflowCard.vue`
  (Dupliquer → modal ; + « Publier comme template » gated), `CreateWorkflowDialog.vue`
  (select placeholder → lien « Parcourir les templates »), `BuilderTopbar.vue` (bouton
  « Publier »), `pages/workflows/Edit.vue` (`await saver.flush()` avant ouverture de la
  modal), `pages/workflows/Index.vue` (prop `templateCategories`, modals câblées,
  commentaire « import phase 9 » erroné corrigé), `types/index.ts`.
- `tests/Feature/Workflows/WorkflowTemplateGalleryTest.php` — retrait du `, false` sur
  `->component('templates/Index')` (la page existe).
- `.knowledge/memory/maps/map-front-files.json` — 10 entrées, 7 descriptions à jour.
- Maquettes : `templates.html` (sections/badges/métra/vedette/empty states/démo alignée
  T1–T3), `workflows.html` (lien galerie), `index.html` (description d'écran).

### Database Changes

Une table : `workflow_templates` (+ 3 index). Aucune modification de schéma existant, aucune
config ni entrée scheduler nouvelle. `DatabaseSeeder` produit les 3 templates système
(`migrate:fresh --seed` vérifié).

### Routes

- `GET templates` → `templates.index` (galerie, page `templates/Index`).
- `POST templates/{template}/use` → `templates.use` (redirect éditeur + toast).
- `POST workflows/{workflow}/publish` → `workflows.publish`.
- `workflows.duplicate` inchangée (l'action de liste existe depuis la phase 3, confirmation
  ajoutée côté front). `php artisan wayfinder:generate --with-form` exécuté.

### Frontend Changes

Points de contrat consommés : prop `templates` (projection camelCase exacte, `origin`
string, `nodesCount`, `graph` = payload Phase 3), `nodeTypes`, `permissions` ;
`templateCategories` sur `workflows.index` ; flash toast « Workflow created from
template. » sur `templates.use` ; `errors.graph` (message FR du validator) rendu dans la
modal de publication. Aperçu de graphe : SVG statique pur et testé (pas de VueFlow — une
instance par carte était injustifiée). Fidélité maquette : sections, chips, vedette
`md:col-span-2`, badges, empty states (galerie vide / section équipe vide / filtre sans
résultat).

### Tests

Backend : **+47 tests** dans 7 nouveaux fichiers — total suite **726/726, 2 709
assertions**. Front : **+11 specs** (`templatePreview`) — total **138 verts**. PHPStan
niveau 7 : 0 erreur. Pint propre. `types:check` et `build` OK. Le test e2e couvre la chaîne
complète : instanciation depuis le template webhook → `WorkflowValidator` vide → activation
→ `RunWorkflowJob->handle()` → exécution completed + mail envoyé. `WorkflowCrudTest`
(duplication) vert sans modification d'assertion pendant le refactoring.

### Commands Executed

- `search-docs` (Boost) : policies multi-arguments, casts enum — avant le code.
- `composer show --direct`, `make:model -m -f`, `make:enum`, `make:controller`, `make:request`,
  `make:seeder`, `make:test --pest`, `route:list`, `database-schema`, `database-query`.
- Boucles Red → Green par lot (`vendor/bin/pest` périmètre étroit).
- `php artisan migrate` (dev MariaDB), `db:seed --class=TemplateSeeder` ×2 (idempotence),
  `migrate:fresh --seed` (base de démo complète), `wayfinder:generate --with-form`.
- Finales : `vendor/bin/pint --dirty`, `vendor/bin/phpstan analyse`, `php artisan test
--compact` (726/726), `npm run test:unit | types:check | build`.

## Key Information

### Technical Decisions

Arbitrages A1–A4 et décisions D1–D13 détaillés dans
`.knowledge/memory/plans/phase9-implementation-plan.md`. Résumé :

- **Snapshot = payload Phase 3** (D3) : `WorkflowGraphMapper::snapshot`/`fromSnapshot`
  sont le seul mécanisme de sérialisation — un template ne peut pas diverger du format
  que le builder, le validator et le moteur consomment déjà. Aller-retour snapshot ↔
  formes moteur épinglé par test (≡ `map()`).
- **`use` = voir + créer un workflow** (A4, validé utilisateur) : instancier crée une
  ressource — « use (= view) » seul aurait laissé un membre sans droit de création
  instancier des workflows. `publish` = `update` sur le workflow (permission explicite du
  prompt).
- **Pas de re-keying à l'instanciation** (D4) : l'unicité de `workflow_nodes.key` est
  par workflow — la copie fidèle est triviale et les interpolations (`{{ node.key.output
}}`) restent valides sans réécriture.
- **Webhook immédiatement déclencheable** : `instantiate` crée l'endpoint webhook
  (mécanisme existant) après la transaction — un workflow issu d'un template webhook est
  exécutable sans retouche (prouvé par le e2e).
- **Publication refusée sans exécutabilité** : `publish` valide via `WorkflowValidator`
  AVANT le snapshot ; refus = `TemplateNotPublishableException` → `errors.graph` (message
  FR du validator) ; un template corrompu ne peut jamais exister (test).
- **Catégorie libre** (max 60) plutôt qu'enum — les chips galerie sont dérivées des
  données, aucune liste en dur ; `templateCategories` (workflows.index) alimente les
  suggestions de la modal.
- **Cross-team fixé à 404** : résolution `visibleFor + firstOrFail` — un template d'une
  autre équipe est invisible, pas interdit (cohérent avec le scoping du reste de l'app).
- **Aperçu SVG statique** (D10) : VueFlow rejeté (une instance par carte × N = coût
  injustifié) ; couche pure `templatePreview.ts` testée Vitest, rendu déclaratif sans
  `v-html` (XSS), hauteur par rangées pour garder les nodes à taille maquette lisible.
- **Maquette corrigée d'abord** (flux design-first, arbitrages validés) : sections
  Système/Mon équipe + badge origine (exigés par le prompt, absents de la maquette),
  métriques fantômes remplacées par « N nodes » + catégorie, vedette « Recommandé » ;
  le select placeholder du dialogue de création devient un lien galerie (A1 : chemin
  d'instanciation unique).
- **Duplication déplacée dans le service** (D4) : `WorkflowTemplater::duplicate` reprend
  à l'identique le corps de l'action supprimée — zéro changement comportemental,
  `WorkflowCrudTest` vert sans retouche.
- **Pas de module `ainatrix/`** (D13) : rien ne le justifie — orchestration simple autour
  d'entités et du validator existants.
- **Toast de publication côté front** (D11) : succès → `back()` sans flash, la modal
  affiche son toast avec lien galerie (le redirect `back()` depuis l'éditeur OU la liste
  rendrait un flash ambigu).

### Gotchas & Solutions

- **`use` est un mot réservé JS/TS** : import wayfinder aliasé
  (`import { use as useTemplateRoute } from '@/routes/templates'`).
- **`Gate::authorize('use', [$template, $team])`** : permission à deux arguments → le
  tableau multi-arguments est obligatoire (sinon la policy reçoit un mauvais $team).
- **AssertableInertia `->component('templates/Index', false)`** : le `false` désactivait
  la vérification du fichier de page tant que la Vue n'existait pas — retiré dès la page
  livrée (la vérification passe).
- **Snapshot périmé depuis l'éditeur** : la modal de publication n'est ouverte qu'après
  `await saver.flush()` — échec de flush = modal non ouverte ; jamais de snapshot d'un
  graphe non sauvegardé.
- **Faux positifs P1013 (« Undefined method actingAs »)** de l'analyse statique Pest,
  préexistants dans toute la suite — non traités (hors périmètre).
- **MariaDB** : noms d'index auto de `workflow_templates` tous < 64 caractères (gotcha
  phase 8 revérifié sur la migration réelle).

### Commands & Config

Aucune variable `.env` nouvelle, aucune entrée `config/` nouvelle. Commandes utiles :
`php artisan db:seed --class=TemplateSeeder` (idempotent, `firstOrCreate` sur
`[origin, name]`), `php artisan migrate:fresh --seed` (base de démo : équipe, workflows,
exécutions, intégrations, 3 templates système), `php artisan wayfinder:generate
--with-form`.

### Version Notes

- Gate multi-arguments : `Gate::authorize('ability', [$model, $extra])` passe les
  arguments au-delà du premier à la méthode de policy (confirmé par search-docs).
- `#[Fillable]` (attribut natif) utilisé sur le modèle — convention du projet pour les
  modèles récents.

## Future Ideas (Not Planned)

- **Marketplace publique de templates** (hors équipes) : visibilité inter-équipes avec
  modération — à évaluer après le dashboard (phase 10) quand l'usage sera mesurable.
- **Import/export de templates (JSON)** : compléterait l'import de workflow resté
  informatif — pertinent en phase 11 (durcissement : validation stricte du JSON importé).
- **`uses_count` / notes & avis** : métriques d'usage réelles (rejetées aujourd'hui comme
  YAGNI, la maquette affiche des données disponibles) — à réévaluer avec le dashboard.
- **Fork de template** et édition des templates système côté admin.
- **Aperçu interactif du graphe** (zoom/pan dans une feuille plein écran) — réutiliser la
  couche canvas de l'éditeur si le besoin émerge.
- **Catégories administrables** (enum piloté en base) si la libre saisie produit trop de
  bruit.
- **Suggestions de catégories dans la modal depuis l'éditeur** (prop `templateCategories`
  sur `WorkflowController@edit`, aujourd'hui liste vide volontairement).

## Known Limitations

- Un template d'équipe publié avec `integration_id` copie la référence telle quelle :
  une équipe instanciatrice sans cette intégration obtient une erreur d'exécution
  explicite (`integration_not_found`) — comportement accepté par le domaine.
- Pas d'édition ni de suppression de template (`delete` définie dans la policy, non
  routée) ; pas de déduplication à la republication (chaque publication crée un
  template).
- La modal de publication depuis l'éditeur ne propose pas de suggestions de catégories
  (pas de prop `templateCategories` sur `edit`).
- L'import de fichiers JSON dans le dialogue de création reste informatif (non
  implémenté, hors périmètre de la phase).
- `npm run check` global échoue sur le formatage PRÉEXISTANT de `.ai/rules/*.md` et
  `docs/reports/phase7-8/report.md` (hors périmètre).

## Next Phase

Phase 10 — Dashboard & UX : KPIs par équipe, exécutions récentes, notifications in-app,
passe UX et responsive (maquettes `dashboard.html` + passe transversale `index.html`).
