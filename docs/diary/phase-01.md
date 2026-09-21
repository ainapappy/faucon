# Phase 1 — Architecture & fondations

**Date** : 2026-09-18 · **Rapport** : [docs/reports/phase1/report.md](../reports/phase1/report.md)

## Le récit

Cette phase n'a produit **aucune fonctionnalité**, et c'était le but. Je suis parti d'un
squelette (Laravel 13, Inertia v3, Fortify, un système d'équipes complet) et de la volonté de
tout auditer avant d'écrire la moindre ligne métier : versions réelles, conventions effectives,
scoping des ressources, emplacement du futur moteur.

Le premier enseignement est arrivé tout de suite : **la suite tournait à 74 tests rouges**. La
cause, une seule — les colonnes 2FA de Fortify manquaient au schéma, et la factory les écrivait.
Une migration copiée du vendor, et tout est repassé au vert. Deux jours plus tôt, j'aurais
« simplement » désactivé ces tests ; c'est exactement le genre de dette invisible que cette
phase voulait traquer.

Deuxième trouvaille, plus sournoise : une ligne parasite insérée avant `<?php` dans
`config/database.php` par un outil BDD. Elle émettait une sortie à chaque chargement de config —
de quoi corrompre des réponses HTTP entières. Un fichier corrompu en silence, voilà le genre de
chose qu'un audit d'ouverture détecte et qu'on ne verrait qu'en production.

## Ce que j'ai retenu

- Poser les **décisions de fondation par écrit** (scoping team-scoped, nommage `{category}.{type}`
  des nodes, cycles de vie à deux états) avant d'écrire le code évite de les re-trancher sous
  pression dix fois ensuite.
- Un squelette « qui marche » n'est pas un squelette sain : **74 tests rouges se cachaient
  derrière un build vert**.
- Le vrai livrable d'une phase d'audit, c'est la connaissance — consignée, datée, relisible.
