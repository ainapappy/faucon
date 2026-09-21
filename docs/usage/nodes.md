# Référence des nodes

Les 17 types de nodes du catalogue, catégorie par catégorie. L'ordre des catégories est
figé — c'est le même partout dans l'interface, avec une couleur et une icône par catégorie.

> 14 types sont exécutables aujourd'hui. **3 types sont annoncés au catalogue mais pas encore
> exécutables** (aucun handler enregistré : `logic.filter`, `action.message`, `action.delay`) —
> un graphe qui les utilise est refusé à la validation avec l'erreur `handler_missing`. Voir le
> [guide d'extension](../technical/extension/ajouter-un-type-de-node.md) pour en ajouter.

## 🟠 Triggers — le départ (un seul par graphe)

| Node         | Config                                                  | Variables produites                           |
| ------------ | ------------------------------------------------------- | --------------------------------------------- |
| **Manuel**   | rien                                                    | l'objet passé au lancement (bouton Run, test) |
| **Webhook**  | URL dédiée (section dédiée de l'inspecteur)             | le corps JSON POSTé, l'en-tête `X-Request-Id` |
| **Planifié** | expression **cron** (ex. `0 8 * * *` = 8 h chaque jour) | la date d'échéance                            |

Le webhook répond **202** immédiatement et exécute en tâche de fond ; le même
`X-Request-Id` reposté dans la fenêtre d'idempotence (1 j) ne crée pas de second run.

## 🔵 Data — façonner les données

| Node               | Config                 | Rôle                                                  |
| ------------------ | ---------------------- | ----------------------------------------------------- |
| **Entrée**         | nom de la variable     | déclare une donnée d'entrée, typée par le JSON reçu   |
| **Transformation** | expressions de mappage | recopie/renomme des champs (`{{ x.a }}` → `sortie.a`) |
| **Sortie**         | rien                   | point final explicite : fige la sortie du run         |

## 🟣 Logique — router le flux

| Node          | Config                                                                   | Comportement                                                                             |
| ------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------- |
| **Condition** | expression `{{ … }}`, **opérateur** (`==`, `!=`, `>`, contient…), valeur | deux sorties typées : `true` / `false` ; les branches non prises sont marquées `skipped` |
| **Filtre** ⏳ | —                                                                        | _annoncé, non encore exécutable_                                                         |

## 🟢 IA — l'intelligence, multi-fournisseurs

Un seul node paramétré par un **mode**, cinq façons de l'utiliser :

| Node               | Mode             | Config typique                                    |
| ------------------ | ---------------- | ------------------------------------------------- |
| **Prompt**         | `prompt`         | un prompt libre, renvoie la réponse demandée      |
| **Classification** | `classification` | une liste de **labels** ; renvoie le label choisi |
| **Extraction**     | `extraction`     | un schéma de champs (`text`, `number`, `enum:…`)  |
| **Résumé**         | `summarization`  | un contenu à condenser                            |
| **Génération**     | `generation`     | un brief ; renvoie un contenu rédigé              |

Commun à tous : le **modèle** (`fournisseur/modele`), la **température**, le plafond de tokens.
La réponse est un **JSON garanti** (structured output : le fournisseur est relancé une fois si
le format dérive). L'**usage en tokens** apparaît dans l'output du node (`usage`).

> Par défaut le runner parle au **provider fake** (réponses déterministes, sans clé) — voir
> [brancher un fournisseur](integrations.md#brancher-un-fournisseur-ia).

## 🟤 Actions — agir sur l'extérieur

| Node                 | Config                                                                                                   | Rôle                                                   |
| -------------------- | -------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ |
| **Requête HTTP**     | méthode, URL, en-têtes, corps, **intégration** optionnelle (auth), politique d'échec (`fail`/`continue`) | appelle une API ; la cible est protégée contre le SSRF |
| **Email**            | destinataire, sujet, corps (variables acceptées), **intégration SMTP** optionnelle                       | envoie un e-mail texte                                 |
| **Message** ⏳       | —                                                                                                        | _annoncé, non encore exécutable_                       |
| **Temporisation** ⏳ | —                                                                                                        | _annoncé, non encore exécutable_                       |

La politique d'échec décide si une erreur HTTP arrête le run (`fail`, défaut) ou passe à la
suite (`continue`).

---

_Erreur dans une config ? La validation du node s'affiche dans l'inspecteur à la sauvegarde —
les messages sont en français et nomment le champ en cause._
