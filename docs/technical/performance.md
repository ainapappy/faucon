# Performance

La phase 13 a mesuré avant d'optimiser — et a surtout **épinglé** ce qui était déjà bon. Cette
page documente les décisions mesurées et leurs bornes, pour ne pas les casser par accident.

## Backend : budgets de requêtes

Les budgets épinglés par `tests/Feature/QueryBudgetTest.php` (et `DashboardTest`) :

| Page / prop                 | Requêtes | Notes                                                              |
| --------------------------- | :------: | ------------------------------------------------------------------ |
| `workflows.index`           |    3     | constant quel que soit le volume (preuve 5→15)                     |
| `workflow-executions.index` |    4     | 7 avec deep link `?execution=` (+3 = find + 2 eagers, contrat 7/8) |
| prop racine `notifications` |    2     | count + take(5)                                                    |
| `dashboard`                 |    5     | pin phase 10                                                       |

Règles appliquées partout : eager loading explicite, `DashboardMetrics` en requêtes agrégées SQL
(jamais d'agrégation PHP), `ExecutionPresenter` unique pour la projection liste des exécutions.

## Payloads Inertia

- **`templates.index` sert un graphe aminci** (`previewGraph`) : nodes `key/type/name/positions`,
  edges `source/target` — les configs (prompts IA, headers HTTP…) **ne quittent jamais le
  serveur**. Gain mesuré : −38 % sur la part `graph` de la galerie système seedée (jusqu'à >50 %
  sur des graphes à configs lourdes). Contrat front : `TemplatePreviewGraph` +
  `buildPreviewLayout` — épinglé par test (`toEqual` du layout complet).
- **`nodeTypes` (≈10,8 kB JSON)** reste une prop **de page** (workflows/builder), délibérément
  pas racine : le passer en racine l'enverrait sur toutes les pages (auth, settings…). Statu quo
  mesuré, refus argumenté (phase 13).
- Badge notifications : 2 requêtes indexées à chaque rendu authentifié — statu quo assumé,
  épinglé, la fenêtre SQL non idiomatique ne valait pas ~2 requêtes hors chemin critique.

## Front : bundle client

| Décision                                                                                                       | Résultat mesuré (phase 13)                                                                                                              |
| -------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| **Echo/pusher-js éliminés du bundle prod** (garde `import.meta.env.DEV` + import dynamique de `realtimeDebug`) | chunk d'entrée 53,3 → **34,5 kB gzip** (−35 %) ; `grep pusher public/build/assets/*.js` = 0 fichier (borne dure)                        |
| **`advancedChunks` refusé**                                                                                    | sondage de 98 noms-hash sur 2 builds : le chunking rolldown par défaut est déjà hash-stable — la config aurait créé du risque sans gain |
| SSR vérifié                                                                                                    | `npm run build:ssr` : 0 fichier pusher côté serveur                                                                                     |

Le seul consommateur d'Echo reste le debug Reverb **DEV-only** (`realtimeDebug.ts`, point
d'entrée unique — tout futur `useEcho` devra passer après son initialisation). Une
réintroduction du temps réel en production se fera avec import statique assumé.

## Où mesurer

```bash
npm run build                    # chunks + manifest
for f in public/build/assets/*.js; do … gzip …; done   # tailles par chunk
grep -l pusher public/build/assets/*.js                # borne : doit rester vide
XDEBUG_MODE=coverage php artisan test --coverage       # couverture (ponctuel)
php artisan test --compact --filter=QueryBudgetTest    # budgets SQL
```

Gotchas de mesure consignés en phase 13 : les binaires gzip varient ~1,2 % entre environnements
(comparer même-binaire) ; le one-shot de rolldown peut réattribuer un lot de hashes sur un build
avant de se stabiliser (3 builds identiques = stable).

## Ce qui n'est PAS optimisé (assumé)

- Le contrat `execution` + polling de la page Exécutions (7 requêtes au deep link) : épinglé,
  non « amélioré » — le contrat phase 7/8 prime.
- Pas de defer/optional ajouté en phase 13 : aucun état de chargement nouveau ne valait le
  changement de comportement.

_Modifier une de ces zones ? Le test de budget correspondant doit rester vert — sinon c'est un
contrat à renégocier explicitement, pas un chiffre à mettre à jour en silence._
