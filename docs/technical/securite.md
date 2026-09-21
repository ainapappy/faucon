# Sécurité

La phase 11 a audité systématiquement l'application (15 constats tracés S1…S15 : 9 corrigés,
6 acceptés avec justification écrite — voir l'[audit](../reports/phase11/audit.md) et son
[rapport](../reports/phase11/report.md)). Cette page documente l'état **actuel** : les mécanismes,
où ils vivent, quel test les épingle.

## Autorisation

- **Team-scoped systématique** : routes métier sous `{current_team}` + middleware
  `EnsureTeamMembership` ; les 5 Policies (`Workflow`, `WorkflowExecution`, `Integration`,
  `Team`, `WorkflowTemplate`) refusent hors équipe et délèguent aux 13 permissions de rôle
  (`TeamPermission`, format `domaine:action`).
- Matrice complète testée (« un non-membre ne peut rien faire » est une assertion, pas un
  espoir) — `tests/Feature/Security/TeamAccessHardeningTest.php` et les tests de policies.
- Les invitations portent un code 64 chars, expirant, à usage unique.

## Validation et entrées

- **Form Requests** partout (17) : validation + autorisation avant le contrôleur ; whitelist de
  config des nodes **revalidée à la sauvegarde du graphe** (S3) et configs contrôlées par
  handler à l'exécutabilité.
- Webhook public : payload plafonné (**64 Kio**), profondeur JSON **10**, auth par token, 60
  requêtes/min/token (throttle nommé `webhooks`).
- L'activation d'un workflow exige l'exécutabilité (S4) : un graphe cassé ne devient jamais
  actif.

## SSRF (`app/Services/Integration/HttpClient.php`)

Le seul chemin HTTP sortant des nodes — « unique wrapper durci du facade Http » :

| Garde                | Détail                                                                                              |
| -------------------- | --------------------------------------------------------------------------------------------------- |
| Plages IP interdites | loopback, privées, link-local (169.254), CGNAT (100.64/10), multicast, réservées — IPv4 **et** IPv6 |
| **DNS pinning**      | résolution épinglée par `CURLOPT_RESOLVE` (A + AAAA) au moment du contrôle — anti-rebinding (S7)    |
| Redirections         | suivies **manuellement**, revalidées hop par hop, max 2                                             |
| Plafonds             | timeout 10 s / connect 5 s, réponse max **1 MiB**                                                   |
| Testabilité          | DNS injectable — 15 tests d'épinglage (`HttpClientPinningTest`)                                     |

Erreurs typées (`HttpClientException` : `blocked_host`, `invalid_url`, `timeout`…) — sans
refléter la cible interne.

## Secrets

- **Chiffrement au repos** : `Integration.credentials` en `encrypted:array`,
  `WebhookEndpoint.token` en `encrypted`. Conséquence assumée : non requêtables — le lookup
  webhook passe par `token_hash` (SHA-256).
- **Jamais de secret dans une prop Inertia** : l'URL webhook (qui contient un token) est servie
  par un endpoint JSON dédié, gated `workflow:update` ; les credentials ne quittent jamais le
  serveur (résumés non secrets par `IntegrationSummaries`).
- **Redaction des logs à l'écriture** (`SecretRedactor`) : masquage par clés + bornage des
  valeurs. Les messages d'erreur et notifications ne contiennent pas de credential.
- Credentials whitelistés par type d'intégration (S5) : un champ inattendu est refusé, pas
  stocké.

## Rate limiting

| Cible                       | Plafond                         | Config                                   |
| --------------------------- | ------------------------------- | ---------------------------------------- |
| Lancement de run / test-run | 10/min par utilisateur + équipe | `WORKFLOW_RUN_RATE_LIMIT_PER_MINUTE`     |
| Appels IA (budget équipe)   | 30/min par équipe               | `WORKFLOW_AI_RATE_LIMIT_PER_MINUTE`      |
| Webhook public              | 60/min par token                | `WORKFLOW_WEBHOOK_RATE_LIMIT_PER_MINUTE` |
| Mots de passe (Fortify)     | 6/min                           | throttle `user-password`                 |

## HTTP, sessions, headers

- **CSRF** standard Laravel ; unique exemption consciente : `webhooks/*` (machines externes,
  auth par token) — documentée et testée.
- **`SetSecurityHeaders`** (S12) : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin` — épingle par test.
- Sessions et verrous : pilote `database`, `WithoutOverlapping` sur le job d'exécution (S14
  accepté : locks applicatifs suffisants au périmètre).
- XSS : aucune injection HTML non échappée ; le seul `v-html` (QR 2FA, SVG généré localement)
  est accepté et tracé (S15).

## Dépendances

`composer audit` et `npm audit` à 0 advisory en phase 11 ; la CI relance la chaîne complète à
chaque push. Les advisory nouvelles sont à re-vérifier lors des mises à jour.
