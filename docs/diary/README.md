# 🔔 Le journal de développement

Faucon est un projet de portfolio : au-delà du code, ce qui m'a formé, c'est le **chemin**.
Ce journal raconte ce chemin, phase par phase — les décisions, les impasses, les pièges
rencontrés et ce qu'ils m'ont appris. Là où les [rapports de phase](../reports/) sont
exhaustifs et factuels (fichiers, tests, commandes), le journal est court et subjectif :
c'est le récit, pas l'inventory.

## Les entrées

| Phase             | Titre                           | En une phrase                                                         |
| ----------------- | ------------------------------- | --------------------------------------------------------------------- |
| [1](phase-01.md)  | Architecture & fondations       | 74 tests rouges apprennent plus que 74 tests verts.                   |
| [2](phase-02.md)  | Authentification & utilisateurs | L'auth, c'est 20 % de fonctionnalités et 80 % de trous silencieux.    |
| [3](phase-03.md)  | Workflow Builder                | Le jour où le graphe est devenu éditable.                             |
| [4](phase-04.md)  | Workflow Engine                 | Remplacer une simulation par un vrai moteur, sans rien casser.        |
| [5](phase-05.md)  | Actions & intégrations          | Ouvrir la porte au monde extérieur — et la sécuriser.                 |
| [6](phase-06.md)  | AI Provider & AI nodes          | Le contrat phase 4 a tenu : 5 nodes IA, zéro ligne de moteur touchée. |
| [7](phase-07.md)  | Exécution & queue               | Du synchrone à l'asynchrone par encapsulation, pas par réécriture.    |
| [8](phase-08.md)  | Logs & monitoring               | Une source de vérité append-only pour chaque node.                    |
| [9](phase-09.md)  | Templates                       | Un seul format de snapshot, réutilisé partout.                        |
| [10](phase-10.md) | Dashboard & UX                  | Le produit a enfin un visage — et des budgets de requêtes.            |
| [11](phase-11.md) | Sécurité & durcissement         | 15 constats, 9 corrigés, 6 assumés : l'audit comme miroir.            |
| [12](phase-12.md) | Tests                           | 838 tests plus tard, la suite est une carte, pas une collection.      |
| [13](phase-13.md) | Optimisation                    | Mesurer avant d'optimiser — et savoir ne rien faire.                  |
| [14](phase-14.md) | Documentation & finalisation    | Écrire la doc, c'est relire tout le projet.                           |

## Ce que le projet m'a appris (vu d'avion)

- **Un contrat tenu vaut mieux qu'un plan parfait** : le registre de handlers décidé en phase 4
  a absorbé 13 nouveaux types de nodes sans jamais toucher au moteur.
- **Le TDD n'est pas une religion, c'est un filet** : chaque faille de sécurité de la phase 11 a
  été prouvée par un test rouge _avant_ son correctif.
- **« Ne rien faire » est une décision d'ingénierie** : la phase 13 a refusé deux optimisations
  populaires (chunks avancés, amincissement du badge) après mesure.
- **Design-first évite les débats** : corriger la maquette _avant_ le code a tranché chaque
  arbitrage UI en une conversation au lieu de dix.

> Le détail chiffré de chaque phase (fichiers, suites finales, commandes) vit dans
> [docs/reports/](../reports/).
