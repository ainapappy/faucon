# Phase 10 — Dashboard & UX

**Date** : 2026-09-20 · **Rapport** : [docs/reports/phase10/report.md](../reports/phase10/report.md)

## Le récit

La phase du visage : KPIs team-scoped en requêtes agrégées, volume quotidien sur 30 jours en
série contiguë, top workflows, notifications in-app (l'échec d'exécution notifie **l'auteur du
run** — une décision d'arbitrage plus qu'une spec), et la passe UX transversale.

Deux habitudes nées ici ont structuré la suite. D'abord l'**épinglage du budget de requêtes** :
le dashboard doit tenir en 5 requêtes quel que soit le volume, et un test le vérifie en passant
la fixture de 5 à 15 lignes — le premier « test anti-N+1 » du projet, devenu pattern. Ensuite la
proposition **`Inertia::defer()`** pour les analytics : le premier rendu n'attend pas la série
30 jours, elle arrive en différé avec son skeleton.

L'autre découverte du jour : une exception **`Throwable` non attrapée** dans l'import des
analytics — corrigée avec le catch le plus large justifiable, documenté dans le rapport.

## Ce que j'ai retenu

- Un KPI qui coûte 6 requêtes est un design, un KPI qui en coûte 6×N est un bug : **épingle le
  budget, la preuve anti-N+1 s'écrit toute seule**.
- Notifier « l'auteur du run » plutôt « toute l'équipe » évite le bruit qui tue les
  notifications ; l'arbitrage est UX, pas technique.
- La passe UX transversale (états vides, responsive, erreurs FR) révèle toujours plus de
  rugosités que prévu — la prévoir entière, pas en correctifs épars.
