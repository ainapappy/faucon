# 📘 Utiliser Faucon

La documentation produit : construire, exécuter et observer des workflows automatisés — sans
lire le code. Les termes techniques sont écrits en gras et définis dans le
[dictionnaire](../wiki/dictionnaire.md).

## Sommaire

1. [Prise en main](prise-en-main.md) — installer, créer un compte, lancer les données de démo
2. [Workflows](workflows.md) — le builder : graphe, palette, inspecteur, sauvegarde, test
3. [Référence des nodes](nodes.md) — les 17 types, catégorie par catégorie
4. [Exécutions](executions.md) — déclencher (manuel, webhook, planifié), suivre, annuler, logs
5. [Templates](templates.md) — la galerie, utiliser, publier
6. [Intégrations](integrations.md) — credentials, test de connexion
7. [Recettes](exemples.md) — trois workflows complets, pas à pas
8. [FAQ & dépannage](faq.md)

## Dans une phrase

Un **workflow** est un graphe d'étapes (**nodes**) reliées par des flèches (**edges**), qui part
d'un **déclencheur** (trigger) et fait circuler des données d'étape en étape. Vous l'éditez
visuellement dans le builder, vous le testez à la demande, et vous le laissez tourner : chaque
exécution est persistée, journalisée et visible dans le dashboard.

```text
Webhook ──▶ Condition ──urgent──▶ Envoyer un e-mail
                └──autre───────▶ Archiver (Sortie)
```

## Où sont les écrans

| Écran          | À quoi il sert                                            |
| -------------- | --------------------------------------------------------- |
| **Dashboard**  | Vue d'ensemble de l'équipe : KPIs, activité, exécutions   |
| **Workflows**  | La liste de vos workflows, création, duplication          |
| **Builder**    | L'édition visuelle d'un workflow                          |
| **Exécutions** | L'historique et la timeline détaillée de chaque run       |
| **Templates**  | La galerie de modèles système et d'équipe                 |
| **Réglages**   | Profil, sécurité (2FA, passkeys), apparence, intégrations |

Un problème ? Commencez par la [FAQ](faq.md) — et si le terme vous échappe, le
[dictionnaire](../wiki/dictionnaire.md) est là.
