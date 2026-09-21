# Recettes : trois workflows complets

Trois constructions pas à pas, du webhook au run vert. Les versions déjà montées existent comme
**templates système** — ces recettes expliquent ce qu'ils font et comment les refaire vous-même.

## Recette 1 — Triage de demandes urgentes

_Template : « Support client prioritaire » · déclencheur : webhook_

**Objectif** : un outil externe (formulaire, chat) pousse une demande ; si elle est marquée
« urgent », l'équipe reçoit un e-mail immédiatement, sinon on archive.

1. **Webhook** : créez le workflow, ajoutez le trigger Webhook. Copiez l'URL depuis la
   section Webhook de l'inspecteur.
2. **Condition** : expression `{{ trigger.type }}`, opérateur `==`, valeur `urgent`.
3. Branche **true** → **Email** : destinataire `equipe@exemple.fr`, sujet
   `Demande urgente`, corps `Message : {{ trigger.message }}`.
4. Branche **false** → **Sortie** (nommez le node « Archivé »).
5. Enregistrez, puis **testez** : échantillon `{"type": "urgent", "message": "…"}` —
   la branche true s'allume. Repassez `type` à `info` : la branche false prend le relais.
6. Activez, puis cblez l'outil externe sur l'URL. Envoyez deux POST :
   le même `X-Request-Id` deux fois de suite ne crée qu'**un** run (idempotence).

## Recette 2 — Router des leads avec l'IA

_Template : « Traitement de leads (IA) » · déclencheur : manuel_

**Objectif** : classifier un texte entrant (lead / spam / question) et router selon le verdict.

1. **Manuel** (trigger) puis **Entrée** : nommez la variable `lead`.
2. **Classification** (IA) : prompt `Classe ce lead : {{ lead.payload }}`, labels
   `lead, spam, question`, température basse (0.2) pour la stabilité, plafond 512 tokens.
   Sans clé d'API, le provider fake répond tout de même — le graphe se teste de bout en bout.
3. **Condition** : expression `{{ classification.label }}`, opérateur `==`, valeur `lead`.
4. Branche **true** → **Email** de bienvenue ; branche **false** → **Sortie** (« Autre »).
5. Testez avec un payload réaliste : l'output du node Classification (labels + usage en tokens)
   est visible dans le panneau de résultats.

## Recette 3 — La veille du matin

_Template : « Veille du matin » · déclencheur : planifié_

**Objectif** : tous les matins à 8 h, aller chercher un flux, le faire résumer, poser le
résultat en sortie.

1. **Planifié** : expression cron `0 8 * * *` (chaque jour à 8 h ; vérifiez que le
   scheduler tourne — `composer dev` le lance).
2. **Requête HTTP** : méthode GET, URL de votre flux, politique d'échec `continue` — si la
   source est en panne, le run continue plutôt que d'échouer.
3. **Résumé** (IA) : prompt `Résume ce contenu : {{ http.body }}`, température 0.3.
4. **Sortie** : fige le résumé comme sortie du run.
5. Déclenchez d'abord **manuellement** (Run) pour vérifier, puis laissez le cron faire :
   la fiche d'exécution indique `schedule` comme origine.

## À retenir des trois recettes

- **Toujours un test avant l'activation** : le test-run n'écrit rien dans l'historique.
- **Les variables se lisent dans le panneau de résultats** : construisez vos expressions
  depuis les outputs réels, pas de mémoire.
- **La politique d'échec est un choix de résilience** : `continue` pour une source
  optionnelle, `fail` (défaut) quand la suite dépend du résultat.
