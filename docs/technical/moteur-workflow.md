# Le moteur de workflow

Le cœur du domaine vit dans `app/Services/Workflow/`. Il est **pur** : aucune dépendance HTTP
ou base de données — le runner s'exécute aussi bien en synchrone (test-run) qu'au sein du job de
queue. Cette pureté est la propriété fondatrice du design.

## Les pièces

| Classe                | Rôle                                                                                                                                                                   |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `WorkflowRunner`      | Orchestre un run : `validate → traverse → execute → report`. Hooks `beforeNode` (cancellation) et `onNodeResult` (logs), budget `timeoutMs` par run                    |
| `GraphValidator`      | Vérifications statiques du graphe (dont cycles par DFS)                                                                                                                |
| `WorkflowValidator`   | Décide de l'**exécutabilité** sans exécuter : exactement un trigger, types connus **avec handler**, configs valides. Retourne des `ExecutionError`, jamais d'exception |
| `GraphTraverser`      | Traversal BFS depuis le trigger : successeurs filtrés par handle, merge d'inputs, branches non prises = `skipped`                                                      |
| `NodeHandlerRegistry` | La table `type → handler`, remplie explicitement dans `AppServiceProvider::register()`. **L'unique point d'extension**                                                 |
| `Interpolator`        | `{{ chemin }}` par dot-path à charset fermé, échappement `@{{ }}` — aucune évaluation de code                                                                          |
| `ExecutionContext`    | Le store de variables d'un run (lecture par dot-paths)                                                                                                                 |
| `WorkflowGraphMapper` | Convertit le graphe persisté (rows) en shapes moteur, et inversement (snapshots)                                                                                       |
| `ExecutionCancel`     | Flag d'annulation en cache (TTL 1 h), vérifié **entre deux nodes**                                                                                                     |
| `RetryPolicy`         | Décide d'un retry queue-level selon la **raison** (réseau, timeout IA)                                                                                                 |

## Le contrat `NodeHandler`

```php
interface NodeHandler
{
    public function type(): string;                       // ex. 'action.http' — source du type
    public function validate(array $config): array;       // messages FR ; vide = valide
    public function execute(NodeContext $context): NodeResult;
}
```

Le `type()` de la classe **est** l'identifiant du type (pas de double déclaration). Le registre
se remplit à la main :

```php
// app/Providers/AppServiceProvider.php
$registry->register(new Trigger\ManualHandler());
$registry->register(new Trigger\ScheduleHandler());
// … 9 handlers + AiNodeHandler (5 modes via l'enum AiMode)
```

## Catalogue vs registre : la distinction qui compte

- **`NodeCatalog`** (17 types) = ce que l'application **connaît** (palette, validation de
  sauvegarde, prop `nodeTypes`) ;
- **le registre** = ce qui est **exécutable** (14 types aujourd'hui).

Un type catalogué sans handler produit `handler_missing` à la validation d'exécutabilité ; un
type inconnu, `unknown_type`. Le catalogue n'ouvre **jamais** l'exécution par lui-même — le
commentaire du validator est explicite : « only the registry makes any type runnable ».

## Cycle de vie d'une exécution

```text
Déclencheur (manuel | webhook | schedule)
  → StartWorkflowRun (Action unique, Policy + rate limit workflow-run)
      → WorkflowExecution (pending) + RunWorkflowJob dispatché
          → job : running → moteur (hooks cancellation + timeout) → completed | failed
          → logs écrits par ExecutionLogWriter (une row par node PAR tentative + événements)
          → événements applicatifs Started / Completed / Failed
                └─ Failed → notification in-app à l'auteur du run
```

- **Queue** : pilote `database`. Job `RunWorkflowJob` : `tries`/`backoff` pilotés par config
  (`WORKFLOW_EXECUTION_MAX_TRIES`, défaut 2 ; backoff 30 s), `WithoutOverlapping` par exécution,
  `failed()` géré.
- **Timeout** : budget par run (`WORKFLOW_EXECUTION_TIMEOUT_MS`, défaut 120 s), en plus des
  timeouts par appel (HTTP 10 s, IA 30 s).
- **Cancellation** : `POST …/executions/{execution}/cancel` pose le flag ; effectif entre deux
  nodes. Statut `cancelled`, état terminal immuable.
- **Retry par raison** : seules les causes rejouables (config `retryable_reasons`) repartent —
  un timeout réseau peut être rejoué, une config invalide jamais.

## Interpolation

`{{ node.cle.chemin }}` — résolue par `Interpolator` sur la map de variables du run :

- **charset fermé** : la dot-path n'accepte que `[a-z0-9_-]` — jamais de méthode, d'index
  arbitraire ou d'évaluation ;
- **échappement** : `@{{ … }}` rend le littéral ;
- les variables sont produites par le trigger et chaque node (sorties typées, ex.
  `classification.label`, `http.body`, `usage.total_tokens`).

## Logs d'exécution

`workflow_execution_logs` est la **source de vérité** (le front lit les logs, pas un état
caché) :

- `kind` `node` (une row par node **par tentative** — upsert sur `(execution, attempt, node_key)`)
  et `event` (mise en queue, annulation…) ;
- écriture **uniquement** par le job via `ExecutionLogWriter` — le runner émet, ne persiste pas ;
- **redaction à l'écriture** (`SecretRedactor` : clés `password`, `token`, `authorization`…)
  et bornage (`WORKFLOW_LOGS_MAX_STRING_CHARS` 2 000, `MAX_JSON_BYTES` 64 Kio) ;
- wording FR centralisé dans `ExecutionLogMessages` ;
- rétention 30 j (MassPrunable quotidien).

## Templates et snapshots

Un template est un **snapshot** du payload builder (`WorkflowGraphMapper::snapshot`) — un seul
format de sérialisation dans l'application, du CRUD au template. `WorkflowTemplater` assure
`instantiate` (template → workflow neuf), `duplicate` (workflow → workflow) et `publish`
(workflow → template d'équipe, **refusé si non exécutable**). Les templates système sont seedés
et **validés par test**.

## Tâches planifiées (`routes/console.php`)

| Tâche                          | Cadence                           | Rôle                                                                                    |
| ------------------------------ | --------------------------------- | --------------------------------------------------------------------------------------- |
| `workflow-schedule-triggers`   | everyMinute, `withoutOverlapping` | dispatch les workflows à trigger `trigger.schedule` échu (`DispatchScheduledWorkflows`) |
| `model:prune`                  | quotidien                         | purge exécutions (90 j), logs (30 j), requêtes webhook (1 j)                            |
| purge des invitations expirées | quotidien                         | hygiène des invitations                                                                 |

## Comment étendre

Le guide de référence : [Ajouter un type de node](extension/ajouter-un-type-de-node.md). En
résumé : une classe handler + une ligne au registre + l'entrée au catalogue + tests (TDD) —
**jamais** de modification du runner/traverser/validator. Ce contrat a été prouvé deux fois :
5 nodes IA en phase 6, 3 handlers d'action/trigger en phase 5, `git diff` vide sur le cœur.
