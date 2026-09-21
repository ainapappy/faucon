# Phase 5 — Actions & intégrations

**Date** : 2026-09-20 · **Rapport** : [docs/reports/phase5/report.md](../reports/phase5/report.md)

## Le récit

La phase où Faucon a ouvert sa porte au monde extérieur : credentials chiffrés au repos
(`encrypted:array`), garde **SSRF** complète sur le client HTTP (IPv4/IPv6 bloqués, redirections
suivies à la main avec re-validation par hop, plafond de réponse), et trois nouveaux handlers
branchés au registre sans toucher au moteur : `action.http`, `action.email`, `trigger.webhook`.

Le webhook public a imposé une discipline nouvelle : **idempotence** par `X-Request-Id` (table
de déduplication, contrainte unique composite), rate limiting nommé, plafond de payload. Et un
piège UX subtil repéré à temps : l'URL du webhook contient un token — elle **n'apparaît jamais
dans les props Inertia**, un endpoint JSON dédié la sert, et sa régénération est gated par la
permission `workflow:update`.

L'autre leçon a été de process : un arbitrage de nommage (`action.http` plutôt que
`data.http_request`) a d'abord été **corrigé dans les maquettes**, puis dans le code — jamais
l'inverse.

## Ce que j'ai retenu

- Suivre les redirections soi-même est le prix d'une garde SSRF honnête : chaque hop est
  re-validé, sinon la protection saute au premier 302.
- Un secret dans une prop Inertia est un secret publié : le front doit le **demander**,
  pas le recevoir.
- Les handlers ajoutez-vous au registre, point. La preuve par trois nouveaux types en une phase.
