# Phase 4 — Workflow Engine

**Date** : 2026-09-19 · **Rapport** : [docs/reports/phase4/report.md](../reports/phase4/report.md)

## Le récit

La phase qui a remplacé la simulation client du builder par un **vrai moteur** : validation
d'exécutabilité, registre de handlers, interpolation `{{ node.key.chemin }}` par dot-path
sécurisée (jamais d'`eval`), traversal BFS avec branches conditionnelles, merge d'inputs,
fail-fast, timeout. Et le contrat fondateur : le registre est **l'unique point d'extension** —
le runner ne connaît pas les types, seulement l'interface `NodeHandler`.

Le moment de vérité est arrivé plus tard que prévu, en phase 6 : cinq nouveaux nodes IA ajoutés
**sans toucher une ligne** au runner, au traverser ni au validator — `git diff` vide sur tout le
cœur. Ce contrat, décidé ici presque sur un pressentiment, a été le meilleur investissement du
projet.

La suppression de la simulation client a aussi été une leçon de lucidité : un outil qui _ment_
sur l'exécution (simuler ce que le serveur exécutera différemment) est pire que pas d'outil.

## Ce que j'ai retenu

- **L'interpolation sans `eval`** : une dot-path whitelistée qui ne traverse jamais méthodes ni
  propriétés arbitraires — la sécurité se joue dans les détails.
- Décider « le registre est le seul point d'extension » **et l'écrire** rend illégitime
  d'ajouter un `if (type === …)` dans le moteur plus tard.
- Un état de chargement mensonger est une dette UX : mieux vaut exécuter pour de vrai.
