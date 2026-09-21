# Tests & qualité

## La chaîne

```bash
composer test        # Pint + PHPStan + suite Pest complète
composer ci:check    # npm run check + npm run types:check + tests (ce que lance la CI)
npm run test:unit    # Vitest (front)
npm run types:check  # vue-tsc --noEmit
vendor/bin/pest --filter=…   # exécution directe d'un sous-ensemble
```

La **CI** (`.github/workflows/tests.yml`) lance `composer ci:check` à chaque push `main` et PR
(PHP 8.4, Node 22, sqlite de test).

## Suites

| Suite   | Volume (fin phase 13)        | Où                                               |
| ------- | ---------------------------- | ------------------------------------------------ |
| Pest    | 841 tests / 3 316 assertions | `tests/Feature` (61 fichiers), `tests/Unit` (40) |
| Vitest  | 208 tests / 21 fichiers      | `__tests__/` des composables et libs             |
| PHPStan | niveau 7, 0 erreur           | `phpstan.neon`                                   |
| vue-tsc | 0 erreur                     | `tsconfig.json` strict                           |
| Pint    | propre                       | `composer lint`                                  |

Environnement de test : **sqlite `:memory:`** (phpunit.xml), queue `sync`, cache `array`,
mail `array` — aucun service externe. `tests/Pest.php` fournit les helpers globaux
`teamWithMember()` et `graphPayload()`.

## Les patterns qui font la valeur de la suite

### 1. Budgets de requêtes (anti-N+1)

`tests/Feature/QueryBudgetTest.php` épingle le **nombre de requêtes SQL** de pages critiques :

| Page / prop                 |    Budget épinglé    | Preuve                                    |
| --------------------------- | :------------------: | ----------------------------------------- |
| `workflows.index`           |          3           | constant de 5 à 15 workflows              |
| `workflow-executions.index` | 4 (7 avec deep link) | +3 = contrat phase 7/8 épinglé            |
| prop racine `notifications` |          2           | count + take(5), jamais de fetch par item |
| `dashboard`                 |          5           | pin phase 10                              |

Le filtre porte sur les **tables métier** (pas le bruit du framework) et chaque test prouve
l'absence de N+1 en doublant la fixture : si le compte varie, le test échoue.

### 2. TDD strict

Chaque correctif de sécurité (phase 11) et chaque contrat (phases 4-13) a été prouvé **rouge
d'abord** : la faille existe tant que son test échoue. Les guides d'extension gardent cette
règle.

### 3. Tests d'épinglage de contrat

Les contrats stables sont **figés par test** : SSRF (15 tests d'épinglage `CURLOPT_RESOLVE`),
layout SVG des aperçus de templates (`toEqual` du layout complet), payload aminci
`templates.index` (assertions `->missing()` sur les configs), redirection Echo prod (aucun
fichier `pusher` dans le bundle).

### 4. Le fake comme frontière

Aucun test ne parle à un service externe : IA = `FakeProvider`, HTTP sortant = DNS injectable,
mail = pilote `array`, queue = `sync`. La suite tourne partout, vite, déterministe.

## Couverture de code

Mesurée ponctuellement via Xdebug (fin phase 12 : **96,2 %** de l'application) :

```bash
XDEBUG_MODE=coverage php artisan test --coverage
```

Aucun script permanent n'est câblé (choix assumé : la cartographie par module vit dans le
[rapport phase 12](../reports/phase12/report.md)) ; la mesure reste un outil ponctuel.

## Conventions de test

- Pest uniquement (jamais PHPUnit direct) ; feature tests par défaut, unit quand la logique est
  pure.
- Factories avec **états nommés** (`WorkflowExecutionFactory::failed()`,
  `WorkflowNodeFactory::ofType('ai.classification')`) — jamais de modèle monté à la main quand
  un état existe.
- Nommage du comportement, pas de l'implémentation ; messages d'échec en français quand ils
  servent au diagnostic.
- Tout changement de **contrat** (routes, props, formats) exige la mise à jour du test qui
  l'épingle — et du miroir TS correspondant.
