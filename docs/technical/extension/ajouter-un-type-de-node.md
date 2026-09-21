# Ajouter un type de node

Le chemin complet pour ajouter un type de node exécutable — **sans toucher au moteur**. C'est le
contrat fondateur du projet (phase 4), prouvé deux fois : 3 handlers en phase 5, 5 nodes IA en
phase 6, `git diff` vide sur runner/traverser/validator à chaque fois.

Exemple filé : `logic.filter` — présent au catalogue mais **sans handler** : le candidat idéal
pour un premier exercice (l'étape 2 suffirait à le rendre exécutable).

## Les quatre lieux à modifier (backend)

### 1. L'entrée au catalogue — `app/Services/Workflow/NodeCatalog.php`

Le catalogue décrit le type pour la validation de sauvegarde, la palette et les props :

```php
'logic.filter' => new NodeDefinition(
    type: 'logic.filter',
    category: NodeCategory::Logic,
    label: 'Filtre',
    description: 'Ne laisse passer que les items qui satisfont la condition',
    icon: 'filter',              // nom lucide ; déjà mappé côté front pour 'filter'
    input: true,
    outputs: [
        ['id' => 'passed', 'label' => 'Passés', 'position' => 0.3],
        ['id' => 'dropped', 'label' => 'Écartés', 'position' => 0.7],
    ],
    fields: [
        ['key' => 'expression', 'label' => 'Expression', 'type' => 'text', 'required' => true, 'placeholder' => '{{ input.items }}', 'options' => null, 'min' => null, 'max' => null, 'step' => null, 'mono' => true],
    ],
),
```

Points d'attention :

- le format de l'id est **`{category}.{type}`** (snake_case) ;
- `outputs` définit les **handles** : la validation de sauvegarde refusera un edge branché sur
  un handle inconnu ;
- `fields` pilote l'inspecteur front automatiquement (`type` : `text`, `number`, `select`,
  `textarea`… — regardez un champ existant pour la shape exacte).

### 2. Le handler — `app/Services/Workflow/Handlers/{Category}/{Type}Handler.php`

La projection mécanique de l'id : `logic.filter` → `Handlers\Logic\FilterHandler`.

```php
final class FilterHandler implements NodeHandler
{
    public function type(): string
    {
        return 'logic.filter';        // source de vérité du type
    }

    public function validate(array $config): array
    {
        // messages FR ; [] = valide. Validez la config, pas le contexte.
        return isset($config['expression']) && is_string($config['expression'])
            ? []
            : ['expression' => 'L’expression est requise.'];
    }

    public function execute(NodeContext $context): NodeResult
    {
        // Interpolez via le contexte, jamais d'eval ; échouez en ExecutionError.
        return NodeResult::ok(output: [...]);
    }
}
```

Regardez `ConditionHandler` pour la structure d'un node à deux handles, `HttpHandler` pour un
node qui consomme une intégration et gère `failure_policy`.

### 3. L'enregistrement — `app/Providers/AppServiceProvider.php`

Une ligne, c'est tout :

```php
$registry->register(new Logic\FilterHandler());
```

### 4. Le passthrough front — `resources/js/lib/nodeIcons.ts`

**Rien d'obligatoire** : la résolution d'icône a un repli muet. Ajoutez une ligne seulement si
vous introduisez une **nouvelle** icône lucide. La palette et l'inspecteur se remplissent
t seuls depuis la prop `nodeTypes` — n'ajoutez jamais d'id de type en dur côté front
(`lib/nodeCategories.ts` ne connaît que les 5 catégories, figées par design).

## Le protocole TDD (obligatoire)

1. **Red** — `tests/Unit/Workflow/` : le handler en isolation (validate + execute, cas limites)
   puis `tests/Feature/Workflows/` : le graphe de bout en bout (exécution, branches, erreur
   typée). Le test échoue : le type n'existe pas.
2. **Green** — catalogue + handler + registre, dans cet ordre.
3. **Épingler** — si le type introduit un comportement moteur nouveau (ex. deuxième branche
   sortie), demandez-vous d'abord si c'est un cas du traverser existant ; modifier le moteur est
   une décision d'architecte, pas un effet de bord.
4. Vérifier la chaîne : `vendor/bin/pint --dirty --format agent`, `composer types:check`,
   `php artisan test --compact`, `npm run test:unit` si le front a changé.

## Checklist finale

- [ ] id `category.type` cohérent, entrée `NodeCatalog` complète (description FR)
- [ ] handler `final`, messages de validation en FR
- [ ] ligne au registre
- [ ] icône mappée si nouvelle
- [ ] tests unit + feature verts, moteur intact (`git diff` sur `WorkflowRunner`,
      `GraphTraverser`, `GraphValidator` doit rester vide)
- [ ] si le type apparaît dans la doc : [référence des nodes](../../usage/nodes.md) mise à jour
      (retirez le marqueur ⏳ le cas échéant)
