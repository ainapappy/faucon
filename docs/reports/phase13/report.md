# Phase 13 — Optimisation & qualité

## Summary

Phase d'**optimisation sans aucun changement de comportement utilisateur** : même le moindre
état de chargement nouveau a été exclu de la spec (aucun prop passé en defer/optional, aucun
skeleton nouveau). L'état des lieux a confirmé un socle déjà optimisé (phases 4-11) : **aucun
N+1 découvert** — l'absence est désormais **prouvée par des tests d'épinglage de budgets de
requêtes** (workflows.index, workflow-executions.index, prop racine notifications), preuve
anti-N+1 incluse dans chaque test (le compte doit rester constant quand la fixture passe de
5 à 15 lignes). Trois optimisations livrées : **élimination d'Echo/pusher-js du bundle de
production** (code mort en prod — le seul consommateur est le debug Reverb DEV ; le chunk
d'entrée passe de 53,3 à 34,5 kB gzip, −35 %), **payload templates.index aminci** (les
configs de nodes — prompts IA, headers HTTP… — ne quittent plus jamais le serveur ; part
`graph` −38 % sur la galerie système seedée), et une décision mesurée de **ne pas configurer
le découpage avancé** (le chunking par défaut de rolldown est déjà hash-stable, preuve par
sondage de 98 hashes). Suites finales : **Pest 841/841 (3 316 assertions)**, **Vitest
208/208 (21 fichiers)**, PHPStan 0 erreur, vue-tsc 0 erreur, Pint propre, build client + SSR OK.

## Implementation

### Files Created

- `tests/Feature/QueryBudgetTest.php` — 3 tests d'épinglage de budgets de requêtes (pattern
  `DashboardTest` : query log filtré sur le nom de table métier) : workflows.index = **3**
  requêtes quel que soit le volume (preuve 5 → 15 workflows dans le test) ;
  workflow-executions.index = **4** sans deep link / **7** avec (le +3 = find + eager workflow
  + eager logs — contrat phase 7/8 épinglé, pas optimisé) ; prop racine notifications = **2**
  exactement (count + take(5))
- `resources/js/lib/__tests__/realtimeDebug.spec.ts` — 5 tests : le module configure Echo
  lui-même (`configureEcho({ broadcaster: 'reverb' })` une fois), canal
  `VITE_REVERB_APP_CHANNEL || 'debug'`, routage DebugPing → toast, warn sans abonnement si
  Echo non configuré, repli de canal stubbé

### Files Modified

- `app/Http/Controllers/Templates/WorkflowTemplateController.php` — `templates.index` sert
  `previewGraph($template->graph)` : projection amincie au contrat exact du front
  `buildPreviewLayout` (nodes key/type/name/positionX/positionY, edges source/target) ;
  `nodesCount` compte toujours sur le graphe complet ; flux `use`/`publish` intouchés ;
  positions en pass-through int|float (colonne JSON sans cast scalaire — un cast float
  écrirait `100.0` sur le fil et gonflerait le payload)
- `app/Http/Controllers/Workflows/WorkflowController.php` — wrapper privé `nodeTypes()`
  supprimé, `NodeCatalog::all()` appelé directement (cohérence avec le contrôleur Templates ;
  zéro changement de comportement)
- `tests/Feature/Workflows/WorkflowTemplateGalleryTest.php` — +2 assertions `->missing()`
  (`templates.0.graph.nodes.0.config`, `templates.0.graph.edges.0.sourceHandle`) ; les
  assertions `key`/`positionX` existantes restent vertes
- `resources/js/app.ts` — `configureEcho` retiré de l'entrée : import statique supprimé, le
  bloc passe sous `import.meta.env.DEV && typeof window !== 'undefined'` avec import
  dynamique de `realtimeDebug` (élimination statique du bundle de production)
- `resources/js/lib/realtimeDebug.ts` — absorbe `configureEcho({ broadcaster: 'reverb' })`
  en tête d'`initializeRealtimeDebug()` ; TSDoc : le module est désormais le SEUL point
  d'entrée Echo (tout futur `useEcho` devra passer après cette initialisation)
- `resources/js/types/templates.ts` — nouveau type `TemplatePreviewGraph` ;
  `WorkflowTemplateListItem.graph: TemplatePreviewGraph` ; `TemplateGraph` (= payload
  builder) inchangé
- `resources/js/lib/templatePreview.ts` — signature `buildPreviewLayout(graph:
  TemplatePreviewGraph, …)` ; **corps intouché d'une ligne** (assignabilité structurelle)
- `resources/js/components/templates/TemplateGraphPreview.vue` — prop `graph:
  TemplatePreviewGraph`
- `resources/js/lib/__tests__/templatePreview.spec.ts` — +1 test d'épinglage : rendu
  strictement identique depuis le graphe aminci (`toEqual` du layout complet)
- `README.md` — feuille de route : phase 13 → ✅
- `.knowledge/memory/maps/map-front-files.json` — 6 entrées (fichier non suivi git)

Fichier temporaire (créé puis supprimé dans le même lot, absent du working tree) :
`tests/Feature/TemplatesPayloadProbeTest.php` — sonde de mesure M5 (groupe `measurement`,
extraction du JSON `data-page` de la réponse Inertia, mêmes fixture et protocole avant/après).

### Database Changes

Aucune migration, aucune entité nouvelle, aucune factory/seeder nécessaire (les tests
utilisent les factories et états existants). Directive factories/seeders : non applicable.

### Routes

Aucune route modifiée (vérifié `route:list`) — pas de `wayfinder:generate`.

### Frontend Changes

Un seul changement visible côté exécution : Echo/pusher-js ne sont plus chargés en
production. Le comportement DEV est strictement identique (debug Reverb : toast + console à
la réception d'un `DebugPing`) — seule différence, `configureEcho` s'exécute quelques
microsecondes plus tard, au chargement du module debug. Aucun changement visuel : l'aperçu
SVG des templates est épinglé par test (`toEqual` sur le layout complet), le CSS est
bit-identique (même hash sur tous les builds), la maquette n'a pas été touchée (aucun écart
introduit, aucune validation requise).

### Tests

- **Back** : +3 (`QueryBudgetTest`) et +2 assertions de contrat (`WorkflowTemplateGalleryTest`).
  Red prouvé avant Green pour B2 (« Property [templates.0.graph.nodes.0.config] was found
  while it was expected to be missing. »). Suite : **841 tests / 3 316 assertions** (838 + 3).
- **Front** : +6 (5 realtimeDebug + 1 templatePreview), Red prouvé pour le contrat
  `configureEcho` (« expected vi.fn() to be called 1 times, but got 0 times »). Suite :
  **208 tests / 21 fichiers**.
- **Budgets de requêtes épinglés** (filtre sur table métier, pas le bruit framework) :

| Page / prop                         | Compte épinglé | Preuve anti-N+1                 |
| ----------------------------------- | :------------: | ------------------------------- |
| `workflows.index`                   |       3        | identique à 5 et 15 workflows   |
| `workflow-executions.index` (liste) |       4        | identique à 5 et 15 exécutions  |
| `workflow-executions.index` (+ `?execution=`) | 7 (4+3) | +3 = find + 2 eagers (contrat 7/8) |
| Prop racine `notifications`         |       2        | count + take(5), jamais /notif  |
| `dashboard` (pin phase 10)          |       5        | non cassé                       |

### Commands Executed

```bash
npm run build                                   # mesures avant/après (chunks, gzip, manifest)
npm run build:ssr                               # garde SSR, 0 fichier pusher côté serveur
npm run test:unit                               # 21 fichiers / 208 tests
npm run types:check                             # 0 erreur
php artisan test --compact                      # 841 / 3 316
vendor/bin/pint --dirty --format agent          # passed
composer types:check                            # PHPStan 0 erreur
grep -l pusher public/build/assets/*.js         # M7 : 1 → 0 (borne dure F1)
```

## Key Information

### Technical Decisions

- **Echo = code mort en production, éliminé et non « différé »** : le seul consommateur
  d'Echo dans `resources/js` est `realtimeDebug.ts`, importé dynamiquement et uniquement en
  DEV ; aucun `useEcho*` nulle part, la cloche de notifications ne fait ni polling ni push.
  Un chargement dynamique à la première visite authentifiée aurait gardé des octets morts en
  prod — l'élimination statique (garde `import.meta.env.DEV`) les retire tous (M7 = 0).
- **Aucun N+1 à corriger — l'épinglage comme livrable** : audit lignes à ligne déjà fait en
  phases 4-11 ; la valeur ajoutée de la phase est la garde anti-régression chiffrée. Le
  contrat `execution` + polling de la page Exécutions est **épinglé (7) et non optimisé**.
- **`nodeTypes` (10,8 kB JSON, 3 pages) : statu quo** — le passer en prop racine
  l'enverrait sur TOUTES les pages (auth, settings…) ; un partial reload est impossible
  (nécessaire au premier rendu) ; un asset statique est du sur-enginiering (YAGNI).
- **Templates : amincissement serveur, layout client inchangé** — précalculer le layout SVG
  côté PHP dupliquerait l'algorithme TS (double maintenance) ; `Inertia::optional()`/defer
  casserait le premier rendu des cartes. La projection `previewGraph` sert exactement le
  contrat consommé par `buildPreviewLayout`.
- **Notifications : statu quo + épinglage** — différer le badge dégraderait la fraîcheur au
  rendu ; fusionner count + recent exigerait du fenêtrage SQL non idiomatique pour ~2
  requêtes indexées (~ms) hors chemin critique.
- **advancedChunks refusé, preuve à l'appui** : sondage (build → édition d'un commentaire →
  rebuild → comparaison des 98 noms-hash) : les chunks vendor/partagés gardent leur hash sur
  une vraie modification d'app ET sur l'édition de commentaire. Adopter une config aurait
  créé du risque sans gain de cache. Les deux issues étaient des succès ; seule l'absence de
  mesure en aurait été une.

### Gotchas & Solutions

- **Inertia v3 inline la page différemment de v2** : `<script data-page="app"
  type="application/json">{json}</script>` (vendor `View/Components/App.php:29`) et non
  `data-page="{json}"` — la regex de la sonde M5 a été adaptée.
- **`positionX`/`positionY` : cast interdit** — la colonne `graph` est JSON sans cast
  scalaire et `AssertableJson::where()` est un `assertSame` strict : caster en float
  casserait le test existant (`positionX === 100`) ET gonflerait le JSON (`100` → `100.0`).
  Pass-through int|float documenté dans le PHPDoc.
- **`paginate()` émet un count aggregate en plus des lignes** — les budgets l'intègrent
  (executions.index = 4, pas 3 comme l'estimait la spec).
- **Nondéterminisme one-shot de rolldown** : un lot de hashes peut se réattribuer à un
  build donné puis se stabiliser (3 builds consécutifs identiques, source inchangée) — pas
  dû à la source (les commentaires sont strippés en prod). À connaître pour tout audit de
  cache long terme.
- **Variance des binaires gzip entre environnements (~1,2 %)** : comparer les gzip avec le
  même binaire (les deltas de cette phase sont mesurés même-binaire quand c'était possible).

### Commands & Config

- Mesures bundles : `npm run build` puis gzip par chunk
  (`for f in public/build/assets/*.js; do printf "%s raw=%s gzip=%s\n" ...`), fermeture
  d'imports de l'entrée depuis `public/build/manifest.json` (script node ponctuel), et
  `grep -l pusher public/build/assets/*.js` (M7).
- Aucune variable `.env` ni config modifiée ; `vite.config.ts` intouché (F3 refusé).

### Version Notes

- `@inertiajs/vue3` 3.x : l'extraction du JSON de page en test passe par
  `<script data-page="app">` (markup v3), cf. Gotchas.
- rolldown/vite-plus 8 : `build.rollupOptions.output.advancedChunks` est supporté (types
  vérifiés) mais **non utilisé** — décision mesurée (cf. Technical Decisions).
- `@laravel/echo-vue` 2.5 embarque son propre noyau et n'importe que `pusher-js` + vue ;
  `pusher-js` est une peerDependency — désormais il n'est chargé qu'en DEV.

## Future Ideas (Not Planned)

- **Amincissement du chunk d'entrée résiduel** (34,5 kB gzip) : les layouts shell
  (AppLayout/AuthSplitLayout/SettingsLayout → sidebar/header/toaster) et la glue Inertia
  dominent désormais ; les passer en dynamique est un lot à spécifier (arbitrage
  latence/perception, non trivial). Dépend de la décision F1 ci-dessus, pas de celle-ci.
- **Revenu Echo en production** (temps réel sur la cloche, la page Exécutions) : le
  mécanisme DEV-only existant est le point d'entrée documenté ; une réintroduction se fera
  avec un import statique assumé dans le bundle prod.
- **Suppression de la dépendance `laravel-echo`** (^2.5.0, devDependency directe jamais
  importée) : les `.d.ts` d'`@laravel/echo-vue` en importent les types — tester
  `types:check` après retrait. Décision utilisateur, non faite dans la phase.

## Known Limitations

- **M1 = 112,6 kB gzip au-dessus de la borne spéculative (110)** : l'entrée ne contient
  **plus aucun octet d'Echo** (M7 = 0, borne dure, atteinte) ; la borne avait supposé
  retirer ~21 kB gzip de l'entrée, le réel est 18,2-18,9 (pusher-js/echo compressaient
  mieux que la moyenne). Le résidu est du code applicatif non ciblé par la spec.
- **M5 : −38 % sur la galerie système seedée, pas ≥ 50 %** (critère de la spec) : la
  projection est au plancher du contrat front (rien d'amovible sans casser
  `buildPreviewLayout`) ; le gain dépend de la densité de configs des graphes — il dépasse
  50 % sur les graphes dominés par des configs lourdes (prompts IA, headers), la fixture de
  mesure (graphes minimaux) rendait le critère mathématiquement hors d'atteinte.
- Le badge notifications est toujours évalué à chaque rendu de page authentifié (2 requêtes
  indexées) — statu quo assumé et épinglé.
- `npm run check` signale 4 échecs de formatage **préexistants** hors périmètre (rapports
  phase 10/12, 2 specs de phase 12) — non modifiés par la phase, non corrigés (hors scope).
- Pas de CI (reportée, cf. phase 12) : les mesures de cette phase sont des instantanés
  locaux.

## Next Phase

Phase 14 — **Documentation & finalisation** : docs/, guides d'extension, exemples,
nettoyage. Dernière phase de la feuille de route.
