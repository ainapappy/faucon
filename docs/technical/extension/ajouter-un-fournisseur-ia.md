# Ajouter un fournisseur IA

Le domaine métier (nodes IA) ne connaît que le contrat `AiProvider` : ajouter un fournisseur
n'exige **aucune** modification des nodes, du handler ou du moteur.

## Cas 1 — l'API est compatible OpenAI (chat/completions)

C'est le chemin le plus court — `OpenAiCompatibleProvider` fait le travail (Z.ai suit ce chemin) :

1. **Config** — `config/ai.php` : ajoutez un bloc provider dans `providers` :

```php
'mistral' => [
    'enabled' => filled(env('MISTRAL_API_KEY')),
    'key' => env('MISTRAL_API_KEY'),
    'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
    'models' => ['mistral-large-latest', 'mistral-small-latest'],
],
```

2. **Manager** — `AiProviderManager` : mappez le driver vers `OpenAiCompatibleProvider` avec la
   config du provider (une poignée de lignes, regardez `zai`).
3. **Tests** — reprenez le pattern des tests Z.ai : contrat du manager (driver résolu, défaut,
   provider non configuré) + erreurs mappées (`MapsProviderErrors`).

## Cas 2 — l'API a sa propre forme

Écrivez un `Providers/{Nom}Provider implements AiProvider` :

- **une méthode** : `complete(AiRequest): AiResponse` ;
- **erreurs typées obligatoirement** : mappez chaque échec HTTP sur une raison
  `AiProviderException` (`provider_timeout`, `rate_limited`, `auth_failed`…) via le concern
  `Concerns/MapsProviderErrors` — la `RetryPolicy` du runner décide du retry **par raison**,
  votre mapping alimente cette décision ;
- **jamais de structured output ici** : ne « comprenez » pas le JSON vous-même — `AiCompleter`
  impose le format, valide contre le schéma et gère l'unique retry de rappel. Votre provider
  renvoie le texte brut ; la garantie de format est centrale, pas dispersée ;
- timeout respecté (`AI_TIMEOUT`, défaut 30 s) ; aucune clé dans les exceptions (elles finiraient
  dans les logs).

## Communs aux deux cas

- **`.env.example`** : documentez la clé (voir le bloc IA existant).
- **Tests** : le `FakeProvider` couvre la logique métier ; vos tests provider doivent couvrir
  **votre** traduction (requête construite, erreurs mappées, usage en tokens extrait) — avec un
  Http::fake, jamais d'appel réel.
- **Vérification** : `vendor/bin/pint --dirty`, `composer types:check`, `php artisan test
--compact`.

## Checklist finale

- [ ] bloc `config/ai.php` (enabled calculé sur la clé, models listés)
- [ ] driver enregistré dans `AiProviderManager`
- [ ] erreurs mappées sur les raisons `AiProviderException`
- [ ] aucune clé dans exceptions/messages
- [ ] tests provider verts (Http::fake, aucun réseau)
- [ ] le sélecteur de modèles du node IA voit vos modèles (prop `nodeTypes`, `NodeCatalog::flush()`
      en test si la config change)
