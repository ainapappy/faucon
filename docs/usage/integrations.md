# Intégrations et credentials

Une **intégration** est une connexion nommée à un service externe, réutilisable par les nodes
d'action de tous les workflows de l'équipe. Les **credentials** (clés, mots de passe) sont
chiffrés en base et ne ressortent **jamais** : ni dans l'interface, ni dans les logs, ni dans
les erreurs.

## Créer une intégration

Réglages → **Intégrations** → Nouvelle. Deux types :

| Type               | Champs                                                                                         | Utilisé par           |
| ------------------ | ---------------------------------------------------------------------------------------------- | --------------------- |
| **HTTP générique** | nom, schéma d'authentification : Bearer (token), Basic (user/password) ou en-tête personnalisé | node **Requête HTTP** |
| **SMTP / E-mail**  | hôte, port, identifiants, TLS                                                                  | node **Email**        |

Les champs secrets s'affichent vides après création : c'est voulu. L'interface montre le
résumé **non secret** (type d'auth, dernier état du test) — modifier un champ secret **remplace**
la valeur, le laisser vide la conserve.

## Tester la connexion

Le bouton **Tester** appelle le service (requête HTTP ou négociation SMTP) et enregistre le
résultat (date + succès/échec, visible sur la carte). Le message d'échec ne contient jamais le
credential en cause.

## Utiliser dans un workflow

Dans l'inspecteur d'un node **Requête HTTP** ou **Email**, choisissez l'intégration : son
authentification s'ajoute automatiquement à l'appel. Une intégration peut servir plusieurs
nodes et plusieurs workflows ; sa suppression est confirmée et les nodes qui la référençaient
perdent leur authentification.

## Webhooks : l'URL d'un workflow

Un workflow à trigger **Webhook** possède une URL publique dérivée d'un token secret. Points
clés :

- l'URL est consultable **depuis le builder** (section Webhook de l'inspecteur), copiable, et
  sertie par un endpoint dédié — jamais injectée dans les pages ;
- **Régénérer** l'URL (confirmé) rend l'ancienne invalide immédiatement ;
- protégé par défaut : idempotence par `X-Request-Id`, plafond de payload (64 Kio), profondeur
  JSON 10, 60 appels/minute.

## Brancher un fournisseur IA

Les nodes IA n'utilisent pas les intégrations : leur fournisseur se configure **au niveau de
l'application** (`.env`) — c'est un réglage d'instance, pas d'équipe :

```dotenv
AI_PROVIDER=openai            # fake (défaut) | openai | anthropic | zai
OPENAI_API_KEY=sk-…           # ou ANTHROPIC_API_KEY=… / ZAI_API_KEY=…
```

Tant qu'aucune clé n'est posée, le **provider fake** répond : les workflows IA fonctionnent,
avec des réponses déterministes (idéal pour la démo et le développement).

Le **modèle** se choisit par node (`fournisseur/modele`), la température et le plafond de
tokens aussi. Voir la [doc technique IA](../technical/fournisseurs-ia.md) pour les détails
(appels, retry, schémas de sortie, budgets).
