# Templates : partir d'un modèle

Un **template** est un graphe figé en modèle. Deux origines :

- **Système** — fournis avec l'application, visibles de toutes les équipes, non modifiables ;
- **Équipe** — publiés par l'un de vos workflows, partagés à toute l'équipe.

## La galerie

**Templates** (menu principal) liste les deux origines avec filtres, description, catégorie et
un **aperçu du graphe** (miniature SVG). Les configs des nodes (prompts, en-têtes…) **n'y sont
pas** : l'aperçu ne montre que la forme du graphe.

## Utiliser un template

**Utiliser** crée un **nouveau workflow** dans votre équipe courante, en brouillon, avec le
graphe complet (nodes, configs, positions). Vous pouvez tout modifier ensuite — c'est votre
copie, le template d'origine reste inchangé.

Permission requise : créer un workflow (rôle _member_ suffit).

## Publier un workflow comme template

Depuis un workflow : **Publier comme template**. Deux règles :

1. le workflow doit être **exécutable** (un trigger, aucune étape cassée, pas de boucle) — la
   publication est refusée sinon, avec la liste des erreurs de validation ;
2. la publication prend une **photo (snapshot)** du graphe au moment T : changer le workflow
   ensuite ne change pas le template.

Publier un template d'équipe reste réversible : suppression depuis la galerie.

## Les trois templates système

| Template                       | Ce qu'il montre                                                       |
| ------------------------------ | --------------------------------------------------------------------- |
| **Support client prioritaire** | webhook → condition (« urgent ? ») → e-mail immédiat, sinon archivage |
| **Traitement de leads (IA)**   | classification IA d'un lead → routage conditionnel réponse/archivage  |
| **Veille du matin**            | cron 8 h → requête HTTP (politique `continue`) → résumé IA → sortie   |

Chacun s'instancie en un clic et **passe la validation** — ils sont testés comme le reste.
