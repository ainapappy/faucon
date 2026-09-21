# Phase 8 — Logs & monitoring

**Date** : 2026-09-20 · **Rapport** : [docs/reports/phase8/report.md](../reports/phase8/report.md)

## Le récit

Rendre chaque exécution **auditable node par node** : une table `workflow_execution_logs`
source de vérité — une row par node _par tentative_, plus des rows d'événements — écrite
uniquement par le job via `ExecutionLogWriter` (upsert sur le triplet execution/attempt/node).
Le runner, encore lui, est resté pur : un second hook `$onNodeResult` en addition optionnelle.

Le sujet sérieux de la phase a été la **redaction** : un `SecretRedactor` qui masque par clés
(`password`, `token`, `authorization`…) avant toute écriture de log. Principe : ce qui ressemble
à un secret est traité comme un secret — la config « encrypted non requêtable » est remplacée par
un hash, jamais le secret lui-même.

La page Exécutions côté front (timeline + sheet, polling) a consommé les logs tels quels —
la **prop `execution.logs`** a remplacé l'idée initiale d'exposer `result`, trop riche et trop
parlante.

## Ce que j'ai retenu

- **Append-only** est la propriété qui rend un log crédible : rien ne se réécrit, tout se
  rajoute (une row par tentative raconte les retries).
- La redaction **à l'écriture**, pas à la lecture : si le secret n'est jamais stocké, aucune fuite
  de lecture n'est possible.
- La rétention (30 j / 90 j, `MassPrunable`) fait partie du design d'un log, pas d'une
  optimisation tardive.
