# Workflows et builder

Le builder est l'éditeur visuel où un **workflow** prend forme : un graphe d'étapes reliées,
vérifié à chaque sauvegarde, testable à la demande.

## Anatomie d'un workflow

- **Nodes** — les étapes. Chacun a un **type** (ce qu'il fait), un nom, une **configuration**
  (ses champs propres) et une position sur le canvas. La liste complète est dans la
  [référence des nodes](nodes.md).
- **Edges** — les flèches, qui définissent le chemin des données. Certaines se branchent sur un
  **handle** précis : la condition a deux sorties, `true` et `false`.
- **Un trigger, et un seul** — tout workflow démarre par un node de catégorie Triggers (Manuel,
  Webhook ou Planifié). C'est une règle de validation, pas une convention.
- **Pas de boucle** — un graphe cyclique est refusé à la sauvegarde.

## Le builder

| Zone           | Rôle                                                                |
| -------------- | ------------------------------------------------------------------- |
| **Palette**    | Les 17 types de nodes par catégorie — clic pour ajouter             |
| **Canvas**     | Le graphe : déplacer les nodes, relier par clic-glissé, pan/zoom    |
| **Inspecteur** | La configuration du node sélectionné : champs propres à chaque type |
| **Topbar**     | Nom du workflow, statut (brouillon/actif), Enregistrer, Tester, Run |

Les couleurs des catégories sont fixes (design validé pour le daltonisme) : **toujours** icône +
libellé, jamais la couleur seule.

## Les variables : faire circuler les données

Chaque node produit une sortie. Un autre node peut l'injecter dans sa configuration par
**interpolation** :

```text
{{ trigger.email }}            ← le champ email de l'entrée du trigger
{{ classification.label }}     ← le label produit par un node nommé « Classification »
@{{ pas une variable }}        ← l'arobase échappe : texte littéral
```

Le chemin commence par la **clé** du node (visible dans l'inspecteur), puis descend dans sa
sortie. L'inspecteur propose les variables disponibles du run.

## Sauvegarder

**Enregistrer** valide le graphe **en entier** puis l'écrit en une transaction : doublons de
clés, edges orphelins, handles inconnus, configs hors bornes — tout est refusé avec un message
par erreur, ou rien n'est écrit.

## Tester (test-run)

Le bouton **Tester** exécute le graphe **pour de vrai**, en synchrone, sans créer d'exécution
dans l'historique :

1. La modale demande un échantillon d'entrée JSON (validé en direct) ;
2. chaque node s'allume avec son statut réel et sa durée ;
3. l'output de chaque node est consultable, l'inspecteur montre l'output réel du node
   sélectionné.

Le test est possible même sur un workflow à trigger webhook — pratique pour vérifier un graphe
avant de le câbler à l'extérieur.

## Dupliquer, publier

- **Dupliquer** copie le workflow (graphe inclus) dans l'équipe courante, en brouillon.
- **Publier comme template** transforme le graphe en modèle pour l'équipe — la publication est
  refusée si le workflow n'est pas **exécutable** (voir [Templates](templates.md)).

## Activer

Le statut **actif** rend le workflow déclenchable en vrai (bouton Run, webhook, planification).
L'activation exige l'**exécutabilité** : un graphe cassé ne s'active pas, même s'il se
sauvegarde (un brouillon a le droit d'être incomplet).

## Supprimer

La suppression est confirmée et met le workflow à la corbeille (restaurable par un
administrateur technique ; les exécutions passées restent visibles dans l'historique).
