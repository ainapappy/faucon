# Phase 9 — Templates

**Date** : 2026-09-20 · **Rapport** : [docs/reports/phase9/report.md](../reports/phase9/report.md)

## Le récit

Accélérer la création : une galerie de **templates** système et d'équipe, l'instantiation en un
clic, la duplication, la publication. La décision d'ingénierie centrale a été de _ne pas inventer
un format_ : le snapshot d'un template réutilise **exactement** le payload du graphe de la phase 3
(`WorkflowGraphMapper::snapshot` / `fromSnapshot`) — un seul format de sérialisation dans
l'application, du builder à la galerie.

La règle de gouvernance qui a le plus discuté : **publier un template exige qu'il soit
exécutable** — `publish` refuse tout graphe qui ne passe pas le `WorkflowValidator`. On ne
partage pas un workflow cassé, même involontairement. Et côté maquette : l'aperçu des templates
est resté un **SVG statique** calculé, pas un canvas Vue-Flow embarqué — plus léger, suffisant,
testé pixel par pixel avec Vitest.

Le seeder de trois templates système a doublé d'un garde-fou : chaque template seedé **passe le
validateur dans un test** — les données de démo ne peuvent pas pourrir en silence.

## Ce que j'ai retenu

- **Un seul format de graphe**, réutilisé du CRUD au snapshot : la deuxième sérialisation est
  toujours une dette future.
- « Publier = exécutable » transforme une convention en contrainte mécanique — c'est le seul
  niveau auquel une règle de gouvernance survit.
- Un aperçu statique SVG bat un aperçu interactif quand l'interaction n'apporte rien.
