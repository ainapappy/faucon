# 🛠️ Documentation technique

La documentation du code : architecture, contrats, décisions et guides d'extension. Elle suppose
le [dictionnaire](../wiki/dictionnaire.md) connu et parle au développeur qui reprend le projet.
Le « comment ça s'utilise » vit dans la [doc produit](../usage/README.md).

## Sommaire

1. [Architecture](architecture.md) — couches, arborescence, modèles, scoping équipe, conventions
2. [Le moteur de workflow](moteur-workflow.md) — runner, registre, handlers, interpolation, traversal
3. [Fournisseurs IA](fournisseurs-ia.md) — contrat `AiProvider`, structured output, drivers
4. [Sécurité](securite.md) — autorisation, SSRF, chiffrement, rate limiting, redaction
5. [Tests & qualité](tests-et-qualite.md) — Pest, Vitest, budgets de requêtes, CI
6. [Performance](performance.md) — payloads Inertia, bundle client, épinglages mesurés

## Guides d'extension

- [Ajouter un type de node](extension/ajouter-un-type-de-node.md) — le chemin complet, back et front
- [Ajouter un fournisseur IA](extension/ajouter-un-fournisseur-ia.md)
- [Ajouter un type d'intégration](extension/ajouter-une-integration.md)

## Les cinq invariants à connaître avant de coder

1. **Team-scoped partout** : toute ressource métier porte `team_id`, son accès passe par sa
   Policy. Aucune exception depuis la phase 2.
2. **Le registre est le seul point d'extension du moteur** : un nouveau type de node = un
   handler + une ligne d'enregistrement. Jamais de `if (type === …)` dans le runner.
3. **Aucun secret dans une prop, un log ou un message d'erreur** : les credentials sont
   chiffrés, les logs sont redactés à l'écriture, l'URL webhook est servie par un endpoint dédié.
4. **Design-first** : aucun écran ne s'implémente sans sa maquette (`.knowledge/design/`) ;
   un écart souhaitable se corrige d'abord dans la maquette.
5. **TDD** : le comportement décrit ici est épinglé par des tests — toute modification de
   contrat commence par un test rouge.

## Où est la source de vérité

| Question                          | Réponse                                            |
| --------------------------------- | -------------------------------------------------- |
| Quels types de nodes existent ?   | `app/Services/Workflow/NodeCatalog.php`            |
| Quels handlers sont enregistrés ? | `app/Providers/AppServiceProvider.php::register()` |
| Quelles routes existent ?         | `php artisan route:list` (+ `routes/web.php`)      |
| Quelles configs sont réglables ?  | `config/workflows.php`, `config/ai.php`            |
| Que garantit le système ?         | la suite Pest (`php artisan test --compact`)       |
