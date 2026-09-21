# Phase 6 — AI Provider & AI nodes

**Date** : 2026-09-20 · **Rapport** : [docs/reports/phase6/report.md](../reports/phase6/report.md)

## Le récit

Brancher l'intelligence sans épouser un fournisseur : une abstraction `AiProvider` (une méthode,
un contrat), un manager de drivers, un completer qui **garantit le structured output** (JSON
validé, retry automatique quand le modèle répond hors-schéma), et trois providers — OpenAI,
Anthropic, et un **fake de test** qui rend toute la suite indépendante des API réelles.

Les cinq modes de nodes IA (`prompt`, `classification`, `extraction`, `summarization`,
`generation`) sont arrivés au catalogue en une passe. La satisfaction technique du projet tient
dans un `git diff` : **le cœur du moteur est resté vide de toute modification**. Le contrat de la
phase 4 a été prouvé, pas seulement promis.

Décision d'interface qui a fait débat : l'usage en tokens est remonté **en clé `usage` de
l'output** du node, pas dans une table de facturation — simple, visible au bon endroit, et les
logs de la phase 8 le rendront auditable.

## Ce que j'ai retenu

- Le **fake provider** est ce qui permet des tests IA déterministes : on teste la logique métier,
  jamais la créativité d'un modèle.
- Le structured output doit être **une garantie du client IA**, pas un espoir du handler :
  valider + retry au bon niveau évite de dupliquer la plomberie dans chaque node.
- Une abstraction « une méthode » force l'essentiel ; chaque paramètre en plus est une promesse
  qu'il faudra tenir avec tous les fournisseurs.
