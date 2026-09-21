# Phase 14 — Documentation & finalisation

**Date** : 2026-09-21 · **Rapport** : [docs/reports/phase14/report.md](../reports/phase14/report.md)

## Le récit

La dernière phase a été celle où l'on relit tout le projet — parce qu'écrire la documentation,
c'est relire. Deux portes d'entrée sont nées : la doc **d'utilisation** (le produit, sans
jargon) et la doc **technique** (le code, avec ses contrats et ses guides d'extension), reliées
par un **wiki** dont le [dictionnaire](../wiki/dictionnaire.md) définit chaque terme dans le
contexte de Faucon. Et un **journal** — celui que vous lisez — pour garder le récit à côté des
rapports chiffrés.

Le nettoyage a tenu ses promesses modestes : le retrait de la dépendance `laravel-echo`
(décision prise en phase 13, jamais importée directement — ses types ne vivent que dans les
`.d.ts` d'`@laravel/echo-vue`, protégés par `skipLibCheck`) est passé sans casse, et cinq
fichiers traînaient des échecs de formatage connus depuis la phase 13 : tous rattrapés.

Vérifier chaque affirmation de la doc contre le code a produit un effet secondaire précieux :
la découverte que **trois types du catalogue n'ont pas de handler** (`logic.filter`,
`action.message`, `action.delay`). Plutôt que de les taire, la doc les documente honnêtement
comme « annoncés, non exécutables » — et le guide d'extension en fait son premier exercice.

## Ce que j'ai retenu

- Écrire pour **deux lectorats** (produit / technique) force à séparer le « pourquoi » du
  « comment » — et le dictionnaire évite de définir deux fois chaque terme.
- La doc est un **audit de cohérence** déguisé : chaque « il me semble que » est devenu un
  `grep`, et plusieurs avaient besoin d'être corrigés.
- Documenter une limitation (`handler_missing`) vaut mieux que la masquer : la ligne de
  conduite du projet s'applique aussi à la prose.
