# FAQ & dépannage

## Sauvegarde et validation

**« Enregistrer » refuse mon graphe avec plusieurs erreurs.**
Chaque message nomme un node et une cause (edge orpheline, handle inconnu, doublon de clé,
config invalide). Corrigez dans l'ordre — la sauvegarde est tout-ou-rien, votre graphe n'a pas
été modifié.

**Mon workflow se sauvegarde mais ne s'active pas.**
L'activation exige l'exécutabilité : exactement **un trigger**, tous les types **exécutables**
(voir [nodes](nodes.md) — trois types sont encore annoncés sans exécution), configs valides,
aucune boucle. Le message d'activation liste les erreurs de validation.

**J'ai renommé un node, mes variables sont cassées.**
L'interpolation adresse les nodes par leur **clé** (stable), pas par leur nom affiché. Si vous
avez changé la clé, mettez à jour les expressions `{{ … }}` qui la référencent.

## Exécutions

**Mon run reste en `pending`.**
Le worker ne tourne pas : en local, `composer dev` lance app + Vite + queue + scheduler
ensemble. Relancez-le.

**Le webhook répond 202 mais rien ne se passe.**
Dans l'ordre : le workflow est-il **actif** ? le trigger est-il bien Webhook ? l'URL est-elle
celle **actuelle** (une régénération invalide l'ancienne) ? le `X-Request-Id` n'a-t-il pas déjà
été utilisé dans les dernières 24 h (idempotence : le doublon est ignoré volontairement) ?

**Un run s'arrête en `failed` sur un node IA.**
Trois causes typiques : fournisseur non configuré (clé absente), réponse hors format (le
système a déjà retenté une fois), plafond de débit. L'erreur exacte est dans la timeline du
run ; voir [Intégrations & IA](integrations.md#brancher-un-fournisseur-ia).

**Mon exécution a disparu.**
Les exécutions terminées sont purgées après **90 jours** (logs : 30 jours). C'est la rétention,
pas une perte.

**« Annuler » n'interrompt pas le node en cours.**
Voulu : l'annulation est vérifiée **entre deux nodes** — un node démarré va au bout, le suivant
est refusé. Le statut passe à `cancelled` dès que le node en cours finit.

## Nodes et données

**Mes données sensibles fuient-elles dans les logs ?**
Ce qui ressemble à un secret (`password`, `token`, `authorization`…) est masqué **avant
l'écriture** des logs, et les valeurs sont tronquées. Les credentials des intégrations sont
chiffrés en base et jamais renvoyés à l'interface.

**Pourquoi l'URL de mon webhook n'apparaît-elle pas dans la page du workflow ?**
Elle contient un token secret : elle est servie par un endpoint dédié, sur demande explicite,
depuis le builder.

**Une étape « skipped » dans ma timeline ?**
C'est une branche de condition non empruntée : le node existe, le flux n'est pas passé par lui.
Ce n'est ni une erreur ni un oubli.

## IA

**Les nodes IA répondent des choses étranges / identiques.**
Vous êtes sur le **provider fake** (défaut, réponses déterministes sans clé). Pour un vrai
fournisseur : `AI_PROVIDER` + clé d'API dans `.env` — voir
[brancher un fournisseur](integrations.md#brancher-un-fournisseur-ia).

**Que signifie `usage` dans l'output d'un node IA ?**
La consommation en tokens du modèle pour ce node. Elle reste accrochée à l'output — visible
dans la timeline et dans les logs.

## Équipes et comptes

**Je ne vois pas les workflows de mon collègue.**
Vous n'êtes pas dans la même **équipe** (basculez via le sélecteur d'équipe) ou vous n'avez pas
le rôle requis pour l'action visée. Workflows, intégrations, templates et dashboard sont
partagés par équipe, jamais entre équipes.

**J'ai perdu l'accès à une intégration (erreur d'authentification).**
Testez la connexion depuis Réglages → Intégrations : le message d'échec ne révèle jamais le
credential, mais dit quoi corriger (token expiré, hôte injoignable…).

---

_Une question qui n'est pas là ? La [doc technique](../technical/README.md) explique le
« comment ça marche » — et le [dictionnaire](../wiki/dictionnaire.md) traduit le vocabulaire._
