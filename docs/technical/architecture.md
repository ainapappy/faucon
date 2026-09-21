# Architecture

## Vue d'ensemble

Monolithe **Laravel 13 / PHP 8.4** avec frontend **Vue 3 + Inertia v3** : pas d'API REST
séparée, Inertia est la couche de communication (le serveur rend des « pages » nommées avec des
props ; le client les rend). Le routage côté client est **généré** par Wayfinder
(`resources/js/actions/`, `resources/js/routes/`) — jamais d'URL écrite à la main.

```text
Navigateur
   ↓ Inertia v3 (props, partial reloads, defer)
Contrôleurs (app/Http/Controllers)  ← Form Requests (validation + autorisation)
   ↓
Actions (intentions) / Services (logique réutilisable)
   ↓
Eloquent (team-scoped) · Queue (database) · Fournisseurs IA · HTTP sortant durci
```

## Arborescence backend

```text
app/
├── Actions/          # intentions applicatives (CreateWorkflow, StartWorkflowRun, …)
├── Concerns/         # traits métier (HasTeams, règles de mot de passe…)
├── Data/             # DTOs readonly (NodeContext, ExecutionResult, AiRequest…)
├── Enums/            # 10 enums (WorkflowStatus, TeamPermission, AiMode…)
├── Events/           # WorkflowExecution{Started,Completed,Failed} + DebugPing (DEV)
├── Http/
│   ├── Controllers/  # 24 contrôleurs, un domaine par dossier
│   ├── Middleware/   # EnsureTeamMembership, SetSecurityHeaders, SetTeamUrlDefaults…
│   └── Requests/     # 17 Form Requests
├── Jobs/             # RunWorkflowJob
├── Listeners/        # NotifyAuthorOfExecutionFailure
├── Models/           # 13 modèles
├── Notifications/    # invitations équipe, échec d'exécution
├── Policies/         # 5 policies, toutes team-scoped
├── Rules/            # règles de validation réutilisables
├── Services/         # le domaine : Workflow/, Ai/, Integration/ + presenters
└── Support/          # helpers en classes (IntegrationSummaries)
ainatrix/             # namespace réservé aux modules hors standards Laravel (vide)
```

Convention de découpage : **Actions = intentions** (ce que la requête veut), **Services =
logique réutilisable** (ce que le domaine sait faire), **Data = DTOs**, **Support = helpers en
classe**. Un traitement non standard Laravel irait dans `ainatrix/{Nom}` (namespace
`Ainatrix\{Nom}`, qui ne référence jamais `App\`) — décision structurelle, proposée par
l'architecte et validée avant création.

## Modèle de données (13 modèles)

| Modèle                 | Table                     | Points clés                                                            |
| ---------------------- | ------------------------- | ---------------------------------------------------------------------- |
| `User`                 | `users`                   | `HasTeams`, Fortify 2FA + passkeys, `MustVerifyEmail`                  |
| `Team`                 | `teams`                   | route key `slug`, soft deletes, pivot `team_members` (rôle)            |
| `Membership`           | `team_members`            | pivot, cast `role` → `TeamRole`                                        |
| `TeamInvitation`       | `team_invitations`        | route key `code` (64 chars), expiration                                |
| `Workflow`             | `workflows`               | `status` draft\|active, soft deletes, `triggerNode` via NodeCatalog    |
| `WorkflowNode`         | `workflow_nodes`          | `key` unique par workflow, `config` JSON, positions                    |
| `WorkflowEdge`         | `workflow_edges`          | source/target par **clé** (pas de FK nodes — intégrité par validation) |
| `WorkflowExecution`    | `workflow_executions`     | `team_id` dénormalisée, MassPrunable 90 j                              |
| `WorkflowExecutionLog` | `workflow_execution_logs` | append-only, une row par node **par tentative**, MassPrunable 30 j     |
| `WorkflowTemplate`     | `workflow_templates`      | snapshot JSON, `origin` system\|team (invariant avec `team_id` null)   |
| `Integration`          | `integrations`            | `credentials` **`encrypted:array`**                                    |
| `WebhookEndpoint`      | `webhook_endpoints`       | `token` chiffré + `token_hash` (SHA-256) pour le lookup                |
| `WebhookRequest`       | `webhook_requests`        | déduplication idempotence, MassPrunable 1 j                            |

Deux choix de schéma à connaître :

- **Edges par clés, pas par FK** : l'intégrité du graphe est garantie par la validation de
  sauvegarde (transactionnelle), pas par la base — cela permet la sauvegarde entière du graphe
  en une passe.
- **Colonnes chiffrées non requêtables** : on cherche par hash (`token_hash`), jamais par la
  valeur chiffrée.

## Scoping équipe

Tout est **team-scoped** : les routes métier vivent sous le préfixe `{current_team}` avec le
middleware `EnsureTeamMembership` ; les Policies refusent hors équipe ; les 13 permissions
(`TeamPermission`, format `domaine:action`) sont accordées par rôle (`owner`, `admin`, `member`).
L'équipe personnelle (`is_personal`) est l'espace privé créé avec le compte. Pattern complet :
[phase 2](../reports/phase2/report.md) et `.knowledge/decisions/authorization.md`.

## Frontend

```text
resources/js/
├── pages/            # 20 pages Inertia (dashboard, workflows/, executions/, templates/…)
├── components/       # par domaine (builder/, dashboard/, integrations/…) + ui/ (shadcn-vue)
├── composables/      # 15 composables testés (useWorkflowBuilder, useExecutionPolling…)
├── lib/              # logique pure testée (nodeCategories, templatePreview, executionLogs…)
├── layouts/          # AppLayout + layouts auth/settings
├── types/            # miroirs TS des payloads (workflows.ts = miroir du NodeCatalog)
├── actions/, routes/ # générés par Wayfinder — ne pas éditer
└── app.ts            # bootstrap Inertia (Echo y est chargé en DEV uniquement)
```

Règles front : une page = un composant à racine unique ; le catalogue des nodes n'est **jamais
redéclaré** côté front (il arrive en prop `nodeTypes`, seuls l'ordre/catégorie/couleurs des 5
catégories sont codés dans `lib/nodeCategories.ts` — contrat design épinglé par test) ; les
formulaires passent par `useForm` + fonctions Wayfinder.

## Cycle de requête typique

1. `GET /{team}/workflows` → `WorkflowController::index` (props : workflows paginés,
   `nodeTypes`) ;
2. édition dans le builder, sauvegarde `PUT …/graph` → `SaveWorkflowGraph` (validation
   transactionnelle) ;
3. lancement `POST …/run` → `StartWorkflowRun` → création `WorkflowExecution` + dispatch
   `RunWorkflowJob` (202 côté utilisateur) ;
4. le job exécute le **moteur** (pur, en mémoire) et écrit statuts + logs ;
5. la page Exécutions rafraîchit par polling ; en cas d'échec, notification à l'auteur.

## Conventions PHP/TS

PHP 8.4 : attributs natifs `#[Fillable]`/`#[Hidden]` (pas de `$fillable`), promotion de
constructeur, types explicites partout, enums backées, PHPDoc avec array shapes. Formatage
**Pint** obligatoire (`vendor/bin/pint --dirty`), analyse **PHPStan/Larastan niveau 7** à 0
erreur. Front : TypeScript strict, `vue-tsc` à 0 erreur, oxlint (`npm run check`). Ces garde-fous
passent dans `composer test` et dans la CI.
