# Phase 7 — Exécution & queue

**Date** : 2026-09-20 · **Rapport** : [docs/reports/phase7/report.md](../reports/phase7/report.md)

## Le récit

Passer du test-run synchrone aux **exécutions persistées et asynchrones** : entité
`WorkflowExecution`, job `RunWorkflowJob` (queue database, `tries`/`backoff` pilotés par config,
`WithoutOverlapping` par exécution, hook `failed()`), et une action unique `StartWorkflowRun`
par laquelle passent les trois déclencheurs — manuel, webhook, planifié.

La décision dont je suis le plus content : le runner de la phase 4 n'a pas été réécrit, il a été
**encapsulé** — deux paramètres optionnels seulement (un hook `$beforeNode` pour la cancellation
entre deux nodes, un budget `$timeoutMs` par run). Zéro rupture des tests existants. La
leçon des phases précédentes (« étendre par additions optionnelles ») a de nouveau payé.

La cancellation asynchrone est le vrai sujet de la phase : un flag en cache vérifié entre deux
nodes, parce qu'on ne peut pas tuer proprement un node en cours — mais on peut refuser de
_commencer_ le suivant.

## Ce que j'ai retenu

- **Encapsuler plutôt que réécrire** : un moteur pur, adapté par des options optionnelles, reste
  testable en synchrone — la queue n'est qu'un transport.
- Un webhook doit répondre **202 vite** et déléguer : le travail est fait par le worker, pas par
  la requête HTTP entrante.
- Les retries doivent être **par raison** (timeout ≠ erreur node ≠ cancellation) : un retry
  aveugle rejouerait une exécution qui n'échouera jamais.
