# Phase 13 — Optimisation

**Date** : 2026-09-21 · **Rapport** : [docs/reports/phase13/report.md](../reports/phase13/report.md)

## Le récit

Une phase d'optimisation qui a d'abord consisté à **refuser des optimisations**. L'état des
lieux a confirmé un socle déjà propre : aucun N+1 découvert — et l'absence est devenue un
livrable, prouvée par des **tests d'épinglage de budgets de requêtes** (3 requêtes pour
`workflows.index`, quel que soit le volume : le test double la fixture pour le prouver).

Trois gains réels : **Echo/pusher-js éliminés du bundle de production** (code mort en prod, le
chunk d'entrée perd 35 %), le **payload des templates aminci** (les configs de nodes ne quittent
plus jamais le serveur), et une décision argumentée de **ne pas configurer le découpage avancé**
— un sondage de 98 hashes a prouvé que le chunking par défaut était déjà stable.

La leçon d'humilité de la phase : la borne spéculative d'entrée (110 kB gzip) n'a pas été
atteinte (112,6) — parce que la borne supposait qu'Echo compressait moins bien. **Mesurer
remplace les hypothèses**, y compris les siennes.

## Ce que j'ai retenu

- « Ne rien faire » est une décision d'ingénierie **quand elle est mesurée** : advancedChunks
  refusé sur preuve, badge notifications refusé sur coût/bénéfice.
- Un budget épinglé par test vaut mieux qu'une règle « pensez aux N+1 » : la preuve est
  mécanique.
- Écrire la borne **après** mesure, pas avant : les hypothèses de compression sont des paris.
