# Exécutions : déclencher, suivre, comprendre

Chaque exécution réelle est **persistée** : qui l'a lancée, avec quelles données, ce que chaque
node a fait, combien de temps, et pourquoi ça a échoué le cas échéant.

## Les trois façons de déclencher

| Déclencheur  | Comment                                                                                                                                        |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **Manuel**   | bouton **Run** (liste ou builder) + un échantillon JSON d'entrée                                                                               |
| **Webhook**  | `POST` sur l'URL du workflow — l'URL vit dans la section Webhook de l'inspecteur, **jamais dans la page elle-même** (endpoint dédié, copiable) |
| **Planifié** | l'expression cron du trigger — vérifiée chaque minute par le scheduler                                                                         |

À chaque lancement : une exécution est créée en `pending`, mise en **queue**, puis un worker
l'exécute. Le webhook répond 202 tout de suite — le travail continue en tâche de fond.

Les lancements sont **rate-limités** (10 runs/minute par utilisateur et par équipe) : c'est une
protection, pas un quota.

## Les statuts

```text
pending ──▶ running ──▶ completed
               │  │
               │  └──▶ failed      (une erreur de node a arrêté le run)
               └────▶ cancelled    (annulation demandée entre deux nodes)
```

Un run qui a subi une erreur **rejouable** (réseau, timeout du fournisseur IA) repart
automatiquement : les **logs gardent une ligne par tentative**.

## La page Exécutions

- **Liste** : filtres par statut et par workflow, badge de statut, auteur, durée ;
- **Timeline** (panneau latéral) : le déroulé node par node — statut, durée, input/output,
  événements (mise en queue, tentatives, annulation…) ;
- **Annuler** : un run en cours peut être annulé — effectif **entre deux nodes** (le node en
  cours va au bout) ;
- **Rafraîchissement** : la page se met à jour d'elle-même tant qu'un run tourne.

## Les logs, et ce qui y est masqué

Les logs sont la **source de vérité** d'une exécution : append-only (rien ne s'y réécrit), une
ligne par node _par tentative_. Deux garanties :

- **Redaction** : tout ce qui ressemble à un secret (`password`, `token`, `authorization`…) est
  masqué **avant l'écriture** — ce que vous voyez a été nettoyé à la source ;
- **Bornage** : les valeurs sont tronquées (2 000 caractères, 64 Kio JSON) pour garder la page
  lisible.

## Notifications

Si une exécution échoue, **l'auteur du run** reçoit une notification in-app (la cloche en haut
à droite). Les autres membres de l'équipe ne sont pas notifiés — l'échec se voit sur le
dashboard et dans la liste.

## Rétention

Les exécutions terminées sont purgées après **90 jours**, leurs logs après **30 jours**
(purge quotidienne). Comptez sur vos propres exports pour l'historique long.
