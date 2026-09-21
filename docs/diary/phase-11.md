# Phase 11 — Sécurité & durcissement

**Date** : 2026-09-21 · **Rapport** : [docs/reports/phase11/report.md](../reports/phase11/report.md)

## Le récit

Une phase entière sans fonctionnalité, consacrée à l'audit — réalisé en lecture seule par
l'agent architecte — puis aux correctifs. **15 constats** tracés S1 à S15 : 9 corrigés, 6
acceptés avec justification écrite. Chaque correction a été prouvée par un **test rouge avant**
le fix : la faille existe tant que son test échoue.

Le plus gros morceau : le **SSRF anti-rebinding** (S7). Épingler le DNS résolu au moment de la
validation (`CURLOPT_RESOLVE`) par hop, y compris sur les redirections suivies à la main — le
serveur ne peut plus changer d'IP entre le contrôle et l'appel. Ajouté à la garde de la phase 5,
la chaîne SSRF devient honnête de bout en bout.

Les 6 constats acceptés ont été les plus formatteurs : wildcards LIKE, ports autorisés, plafond
IP webhook… chacun **documenté avec sa justification de risque résiduel**. Accepter un risque
explicitement est une décision d'ingénierie ; l'ignorer est une dette.

## Ce que j'ai retenu

- Un audit produit surtout de la **preuve que le reste est conforme** (matrice d'autorisation
  complète, zéro secret dans props/logs) — le rassurant compte autant que les failles.
- Le rate limiting n'est pas cosmétique : runs de workflow et appels IA budgetés, sinon un
  utilisateur pressé F5-detruit-sa-facture.
- « Corrigé » et « accepté » doivent être **également tracés** : la liste des risques assumés
  est un document vivant, pas un aveu.
