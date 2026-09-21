# Phase 12 — Tests

**Date** : 2026-09-21 · **Rapport** : [docs/reports/phase12/report.md](../reports/phase12/report.md)

## Le récit

La phase « tests » a commencé par un aveu confortable : l'état des lieux a montré une suite déjà
dense et saine — tous les modules critiques listés par la spec disposaient de leurs tests. Le
travail a donc été de **cartographier** (838 tests back organisés par module), de combler des
edge cases précis (config JSON corrompue, graphe vide, zéro/unicode/emoji en interpolation,
retry réussi à la 2ᵉ tentative de bout en bout) et d'ajouter **202 tests Vitest** côté front.

Le moment le plus formateur est venu de la **mesure de couverture** (Xdebug, 95,7 % puis 96,2 %
après les ajouts) : elle a révélé un filet de sécurité du runner découvert _non couvert_ par la
mesure — le genre d'angle mort qu'une estimation « à feeling » aurait raté. La cartographie
module par module est depuis une carte vivante, pas une collection de fichiers.

## Ce que j'ai retenu

- Une suite de tests, comme un codebase, a besoin de **structure lisible** : le découpage par
  module est devenu la carte pour naviguer 838 tests.
- La couverture ne dit pas « assez testé » ; elle dit **où l'estimation ment**.
- Les edge cases d'unicode/emoji en interpolation paraissent futiles jusqu'au jour où un nom
  d'utilisateur avec emoji casse une dot-path en production.
