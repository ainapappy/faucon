# Phase 3 — Workflow Builder

**Date** : 2026-09-19 · **Rapport** : [docs/reports/phase3/report.md](../reports/phase3/report.md)

## Le récit

Le cœur du domaine : le jour où le graphe est devenu **éditable**. Schéma `workflows` /
`workflow_nodes` / `workflow_edges`, catalogue des types de nodes (`NodeCatalog`), validation de
graphe (cycles détectés par DFS), sauvegarde transactionnelle, et côté front un vrai canvas
`@vue-flow/core` avec palette, inspecteur et pan/zoom — fidèle à la maquette `builder.html`.

Deux décisions ont structuré tout le reste. D'abord le **format des types de nodes** : la chaîne
`{category}.{type}` (`trigger.manual`, `ai.prompt`…) projetée mécaniquement en classe
`Handlers\{Category}\{Type}Handler` — pas d'enum PHP, qui aurait créé une double source de
vérité et verrouillé l'extension. Ensuite le **design-first** : la maquette a été ajustée _avant_
le code à chaque arbitrage (les écarts de liste assumés, validés avec l'utilisateur), jamais
après.

## Ce que j'ai retenu

- **Le nommage des types est un contrat d'architecture**, pas du cosmétique : `trigger.manual`
  → `Trigger\ManualHandler` a rendu l'extensibilité quasi gratuite dès la phase 6.
- Une sauvegarde de graphe doit être **strictement transactionnelle** : edges orphelins,
  handles inconnus, doublons — tout est refusé, rien n'est à moitié écrit.
- Le catalogue exposé au front comme props a évité toute duplication de la liste des types
  (le front ne devine jamais ce que le serveur accepte).
