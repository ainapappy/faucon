# Documentation Faucon

Bienvenue dans la documentation du projet Faucon. Elle est organisée en **deux portes d'entrée**
selon votre profil, plus deux espaces transversaux :

| Espace                              | Pour qui                     | Contenu                                                                          |
| ----------------------------------- | ---------------------------- | -------------------------------------------------------------------------------- |
| [📘 Utilisation](usage/README.md)   | Utilisateurs du produit      | Premier pas, builder, exécutions, templates, intégrations, recettes, FAQ         |
| [🛠️ Technique](technical/README.md) | Développeurs / contributeurs | Architecture, moteur, IA, sécurité, tests, performance, guides d'extension       |
| [📖 Wiki](wiki/README.md)           | Tout le monde                | **Dictionnaire** des termes techniques ([dictionnaire.md](wiki/dictionnaire.md)) |
| [📔 Journal](diary/README.md)       | Curieux du projet            | Le journal de développement, phase par phase                                     |
| [📋 Rapports de phase](reports/)    | Relecture / audit            | Un rapport détaillé par phase du roadmap (format standardisé)                    |

## Comment naviguer

- Vous voulez **utiliser** Faucon : commencez par la [prise en main](usage/prise-en-main.md).
- Vous voulez **comprendre ou étendre le code** : commencez par [l'architecture](technical/architecture.md).
- Un **mot technique** vous échappe (node, handler, team-scoped, SSRF…) : cherchez-le dans le
  [dictionnaire du wiki](wiki/dictionnaire.md) — chaque terme y est défini dans le contexte de
  Faucon, avec des liens vers la doc détaillée.

## Convention

- La doc d'**utilisation** parle produit : elle évite le jargon et renvoie au dictionnaire quand
  un terme technique est incontournable.
- La doc **technique** parle code : chemins de fichiers réels, contrats, tests qui épingle le
  comportement décrit.
- La source de vérité du comportement reste le **code + ses tests** : si la doc et le code
  divergent, signalez-le — et en cas de doute, faites confiance aux tests.
