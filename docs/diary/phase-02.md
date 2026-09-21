# Phase 2 — Authentification & utilisateurs

**Date** : 2026-09-19 · **Rapport** : [docs/reports/phase2/report.md](../reports/phase2/report.md)

## Le récit

L'auth « existait déjà » — Fortify, équipes, invitations. La phase a consisté à vérifier chaque
parcours et à combler les trous : registration avec redirection vers la vérification email, 2FA
TOTP complète, passkeys de bout en bout, et le pattern d'autorisation des futures ressources
métier.

Le piège le plus instructif : un **500 latent sur `GET /user/confirm-password`** — le callback
`confirmPasswordView` n'était simplement pas branché. Rien ne l'indiquait : aucun test, aucune
erreur au log, juste une route morte si quelqu'un l'appelait. L'audit de couverture réelle
(réalisée avant d'écrire du code) l'a fait tomber en quelques minutes.

L'autre leçon, celle qui a servi à toutes les phases suivantes : formaliser **le gabarit
d'autorisation** des ressources team-scoped (Policy + permissions + scoping par relation) une
seule fois, sous forme de décision documentée. Les phases 3 à 10 n'ont plus jamais rediscuté la
question — elles ont appliqué.

## Ce que j'ai retenu

- En auth, **les trous silencieux** (route non branchée, interface manquante) sont plus
  dangereux que les bugs bruyants : il faut des tests qui _prouvent_ les parcours, pas qui
  les supposent.
- Un « un non-membre ne peut rien faire » testé explicitement sur `TeamPolicy` vaut tous les
  commentaires d'intention du monde.
- Décider **une fois** du pattern d'autorisation, le documenter, l'appliquer mécaniquement :
  la cohérence est un livrable.
