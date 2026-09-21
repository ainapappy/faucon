# Fournisseurs IA

L'abstraction IA vit dans `app/Services/Ai/` : le domaine métier (les nodes IA) ne connaît
**aucun** fournisseur concret — seulement un contrat, un manager et un completer.

## Le contrat

```php
interface AiProvider
{
    public function complete(AiRequest $request): AiResponse;
    // lève AiProviderException (raisons typées : provider_timeout, unreachable,
    // rate_limited, auth_failed, invalid_request, error, not_configured)
}
```

`AiProviderManager` est un manager Laravel (singleton) : le driver actif vient de
`AI_PROVIDER` (défaut `fake`). Quatre drivers :

| Driver      | Classe                                        | Notes                                            |
| ----------- | --------------------------------------------- | ------------------------------------------------ |
| `fake`      | `FakeProvider`                                | déterministe, sans clé ni réseau — tests et démo |
| `openai`    | `OpenAiProvider` → `OpenAiCompatibleProvider` | `POST {base_url}/chat/completions`               |
| `zai`       | `ZAiProvider` → idem                          | Z.ai / GLM, API compatible OpenAI                |
| `anthropic` | `AnthropicProvider`                           | `POST {base_url}/v1/messages`, header versionné  |

`OpenAiCompatibleProvider` centralise la traduction (requête, erreurs via le concern
`MapsProviderErrors`) pour tous les drivers compatibles OpenAI.

## Structured output : la frontière unique

**`AiCompleter`** est le seul chemin d'appel IA du domaine :

1. il impose le format JSON dans l'instruction (grammaire `AiJsonSchema` :
   `{ champ: text | number | boolean | enum:v1,v2 }`, consignes en FR) ;
2. il appelle le provider ;
3. il valide la réponse contre le schéma ;
4. en cas de dérive : **un seul retry** avec rappel de format, puis
   `AiStructuredOutputException` (raison typée).

Conséquence : aucun handler de node ne gère de plomberie de format — la garantie est
structurelle, pas discipline.

## Les cinq modes

Un seul handler, `Ai\AiNodeHandler`, paramétré par l'enum `AiMode` — registre : cinq
enregistrements, une classe :

| Mode                | Sortie typique                       | Config node spécifique              |
| ------------------- | ------------------------------------ | ----------------------------------- |
| `ai.prompt`         | texte/réponse libre demandée         | prompt                              |
| `ai.classification` | `{ label }` parmi les labels fournis | labels (liste)                      |
| `ai.extraction`     | objet conforme au schéma de champs   | schéma (`text`, `number`, `enum:…`) |
| `ai.summarization`  | contenu condensé                     | contenu à résumer                   |
| `ai.generation`     | contenu rédigé                       | brief                               |

Commun : `model` (`fournisseur/modele`), `temperature`, `max_tokens` — avec défauts venant de
`config/ai.php` (`AI_TIMEOUT` 30 s, `AI_RETRIES` 2, `AI_MAX_TOKENS` 2048, `AI_TEMPERATURE` 0.7).

## Usage en tokens

`AiResponse` porte l'usage (`AiUsage` : prompt/completion/total). Il remonte **en clé `usage`
de l'output du node** — visible dans la timeline, persisté dans les logs. Pas de table de
métérologie séparée : le choix assumé de la phase 6 (simple, au bon endroit, auditable via les
logs).

## Configuration

```dotenv
AI_PROVIDER=fake            # fake | openai | anthropic | zai
OPENAI_API_KEY=…            # chaque driver est « enabled » si sa clé est présente
ANTHROPIC_API_KEY=…
ZAI_API_KEY=…
# base URLs et version d'API surchargées au besoin (OPENAI_BASE_URL, ANTHROPIC_BASE_URL…)
```

Les modèles par driver vivent dans `config/ai.php` (ex. Anthropic : `claude-haiku-4-5`,
`claude-sonnet-5` ; Z.ai : `glm-4.6`…). Aucune clé ne sort de la config vers les props ou les
logs.

## Budget et abus

Les appels IA passent sous le **rate limit** `ai_calls` (30/min par équipe,
`WORKFLOW_AI_RATE_LIMIT_PER_MINUTE`) posé au lancement de run — un F5 frénétique ne se
transforme pas en facture. Le timeout provider est indépendant du timeout du run.

## Ajouter un fournisseur

Voir [Ajouter un fournisseur IA](extension/ajouter-un-fournisseur-ia.md) : une classe provider
(+ réutilisation de `OpenAiCompatibleProvider` si l'API est compatible), l'entrée dans
`config/ai.php`, les tests sur le fake comme sur le vrai contrat.
