# Phase 6 — AI Provider & AI Nodes

## Summary

La phase branche l'intelligence sur le moteur : une abstraction de fournisseur IA découplée
(`app/Services/Ai/**` — contrat `AiProvider` une méthode, manager de drivers, completer avec
structured output garanti, 3 providers OpenAI/Anthropic/Fake) et les **5 modes de nodes AI**
(`ai.prompt`, `ai.classification`, `ai.extraction`, `ai.summarization`, `ai.generation`) ajoutés
au catalogue **sans toucher au runner** (contrat phase 4 prouvé : `git diff` vide sur
runner/traverser/validator/DTOs moteur). Usage tokens capturé en clé `usage` de l'output des
nodes AI, visible après test run. Inspecteur AI côté front (aide-mémoire des variables, hints
par mode, badge usage tokens), maquette `builder.html` mise à jour en pré-requis (flux
design-first). Zéro migration, zéro route nouvelle, zéro dépendance composer. Suites finales :
Pest **550/550** (1 835 assertions), PHPStan niveau 7 **0 erreur**, Pint propre, Vitest **102
tests verts**, `types:check` et `build` OK, `npm run check` OK.

## Implementation

### Files Created

Backend (`app/`) :

- `app/Data/Ai/AiRequest.php` — requête de complétion immuable (provider, model, prompts,
  température, max tokens, schéma JSON optionnel, timeout). Aucun secret transporté.
- `app/Data/Ai/AiResponse.php` — texte, `structured` (validé, set par le completer), usage,
  provider, model.
- `app/Data/Ai/AiUsage.php` — `promptTokens`/`completionTokens` + `toArray()` snake_case.
- `app/Services/Ai/AiProvider.php` — contrat `complete(AiRequest): AiResponse`.
- `app/Services/Ai/AiProviderManager.php` — `Illuminate\Support\Manager` ; drivers
  `fake|openai|anthropic` depuis `config('ai.default_provider')` ; helper typé `provider()`.
- `app/Services/Ai/AiCompleter.php` — boundary unique : instruction JSON (si schéma),
  validation, **1** retry avec rappel de format, `AiStructuredOutputException` sinon.
- `app/Services/Ai/AiJsonSchema.php` — grammaire du schéma simple (`text|number|boolean|enum:…`)
  pure et statique : `instruction()`, `errors()`, `demoValue()`, `decode()` (fences markdown
  retirés avant `json_decode`). Partagée completer + FakeProvider.
- `app/Services/Ai/Providers/OpenAiProvider.php` — `POST {base_url}/chat/completions`,
  `Authorization: Bearer`, `max_completion_tokens`, `response_format {json_object}` instructif.
- `app/Services/Ai/Providers/AnthropicProvider.php` — `POST {base_url}/v1/messages`, headers
  `x-api-key` + `anthropic-version`, `max_tokens` obligatoire, `system` séparé, concat des blocs
  `content[*]` texte, `input_tokens`/`output_tokens` → usage.
- `app/Services/Ai/Providers/FakeProvider.php` — déterministe (texte démo ; schéma →
  `demoValue()` par champ → classification renvoie la 1ʳᵉ étiquette) + closure injectable pour
  les tests.
- `app/Services/Ai/Providers/Concerns/MapsProviderErrors.php` — construction de la requête avec
  retry transport (`Http::retry(1 + ai.retries, 500, when: 429|5xx|ConnectionException)`) et
  mapping statut → `AiProviderException` (message provider tronqué 500 chars en détail
  technique, jamais les headers, jamais la clé).
- `app/Services/Ai/Exception/AiProviderException.php` — raisons `provider_timeout`,
  `provider_unreachable`, `provider_rate_limited`, `provider_auth_failed`,
  `provider_invalid_request`, `provider_error`, `provider_not_configured` ; 7 constructeurs
  nommés ; `userMessage` FR affichable + `technicalDetail()` pour les logs.
- `app/Services/Ai/Exception/AiStructuredOutputException.php` — erreurs de validation + texte
  brut en `technicalDetail()` (logs uniquement).
- `app/Services/Workflow/Handlers/Ai/AiMode.php` — enum des 5 modes, `typeId()` = `'ai.'.$value`
  (zéro désynchronisation), `expectsStructured()`.
- `app/Services/Workflow/Handlers/Ai/AiNodeHandler.php` — **un** handler paramétré par mode :
  résolution `provider/model` (défaut config, erreurs `ai_model_unavailable`), interpolation du
  template via l'`Interpolator` phase 4 (tel quel), prompts système FR par mode (classification
  : liste d'étiquettes injectée ; extraction : schéma généré depuis les lignes « clé: type »),
  output `text|label|structured` + `usage` ; raisons d'erreur IA préservées.
- `config/ai.php` — provider par défaut (`AI_PROVIDER`, défaut `fake`), timeout/retries/
  max_tokens/temperature, providers avec `enabled` (suivi de la présence de la clé), `models`
  (ids d'API réels), `base_url`, `version` Anthropic.

Frontend :

- `resources/js/lib/aiVariables.ts` — `aiOutputKeysByType`, `isAiNodeType` (segment `ai.`,
  zéro liste de types en dur), `aiModeHint`, `upstreamVariablePaths` (trigger + BFS ancêtres,
  clés de sortie AI réelles, `payload` d'`data.input`).
- `resources/js/lib/aiUsage.ts` — `formatAiUsage(output)` (« Tokens : 128 prompt · 45 réponse »),
  null pour toute forme absente/malformée.
- `resources/js/components/builder/AiInspectorSection.vue` — aide-mémoire repliable des
  variables (chemins réels des ancêtres), hint du mode, note usage.
- `resources/js/components/builder/AiUsageBadge.vue` — badge usage tokens discret.

Maquette (design-first, pré-requis du lot front) :

- `.knowledge/design/js/builder.js` — 5 définitions ai.* (miroir exact du NodeCatalog), options
  composites `provider/model` (fake d'abord), graphe de démo corrigé (`{{ n2.label }}`), badge
  usage dans le tiroir de test.
- `.knowledge/design/builder.html` — inspecteur IA (fuchsia `--cat-3`) : aide-mémoire
  repliable, hints par mode, badge usage (onglet Sorties + tiroir).
- `.knowledge/design/css/app.css` — classes composants `.cheat-toggle/.ai-cheatsheet/…` —
  **aucun nouveau token CSS**.

### Files Modified

- `app/Providers/AppServiceProvider.php` — singleton `AiProviderManager` + boucle
  d'enregistrement des 5 instances d'`AiNodeHandler` (une par `AiMode::cases()`).
- `app/Services/Workflow/NodeCatalog.php` — 15 → **17 types** : `ai.summary` supprimé,
  `ai.summarization` le remplace ; `ai.prompt` et `ai.extraction` ajoutés ; options du champ
  `model` construites depuis `config('ai.providers')` (`aiModelOptions()`, fournisseurs
  `enabled` uniquement, fake en tête) ; `flush()` pour les tests (memoïzation statique).
- `.env.example` — `AI_PROVIDER=fake`, `AI_TIMEOUT=30`, `AI_RETRIES=2`, `AI_MAX_TOKENS=2048`,
  `AI_TEMPERATURE=0.7`, clés fournisseurs commentées (`# OPENAI_API_KEY=`,
  `# ANTHROPIC_API_KEY=`).
- `resources/js/lib/nodeOptionLabels.ts` — options composites `provider/model` affichées
  « model · Fournisseur » (fake → Démo, openai → OpenAI, anthropic → Anthropic), repli brut.
- `resources/js/lib/nodeIcons.ts` — mappings `pen-line` (ai.prompt), `scan-text` (ai.extraction).
- `resources/js/components/builder/NodeInspector.vue` — branche `isAiNode` →
  `AiInspectorSection` (prop `upstreamVariables`) ; `AiUsageBadge` dans l'onglet Sorties.
- `resources/js/components/builder/ExecDrawerNodeRow.vue` — `AiUsageBadge` sous la sortie
  repliable du tiroir de test.
- `resources/js/pages/workflows/Edit.vue` — `upstreamVariables` calculé depuis
  `builder.nodes`/`builder.edges` et passé à `NodeInspector`.

Tests modifiés (alignement catalogue 15 → 17) :

- `tests/Unit/Workflow/NodeCatalogTest.php` — 17 types, répartition [3,3,2,5,4], définitions
  AI exactes, options config-driven + `flush()`, sentinel.
- `tests/Unit/Workflow/WorkflowValidatorTest.php` et
  `tests/Feature/Workflows/WorkflowTestRunTest.php` — fixture « type catalogué sans handler »
  déplacée de `ai.summary` vers `action.delay` (toujours valide pour ce cas).
- `tests/Feature/Workflows/WorkflowCrudTest.php` — compte 15 → 17.
- `resources/js/composables/__tests__/useWorkflowBuilder.spec.ts` — fixture 5 types ai.*,
  `ai.summary` absent, défaut du select → `fake/demo`.
- `resources/js/lib/__tests__/nodeOptionLabels.spec.ts` — specs pattern `provider/model`.

### Database Changes

Aucune. Providers et modèles config-driven (`.env`/`config/ai.php`). La table `ai_providers`
du master §9 n'est pas créée (YAGNI confirmé : aucun besoin UI ; les clés resteraient de toute
façon en `.env`). La table `workflow_nodes` étant vide (vérifié 2026-09-20), le renommage
`ai.summary` → `ai.summarization` n'exige aucune migration ni perte de données.

### Routes

Aucune route nouvelle → `php artisan wayfinder:generate` non requis (diff `routes/` vide).

### Frontend Changes

Voir Files Created / Modified. Le catalogue (`nodeTypes`) reste la source unique servie en
props Inertia — aucune liste de types AI en dur côté front. Sorties des nodes AI lues dans
`NodeRunResult.output` : `text` | `label` | `structured` + `usage: {prompt_tokens,
completion_tokens}` (clés réservées documentées). Aucun nouveau type TS ni endpoint.

### Tests

Backend — 9 fichiers, **87 tests** (TDD Red → Green par lot) :

- `tests/Unit/Ai/AiJsonSchemaTest.php` (15) — instruction FR, erreurs (champ requis manquant,
  mauvais type, label hors enum, clés supplémentaires tolérées), `decode()` (fences, JSON
  invalide, liste), `demoValue()` déterministe.
- `tests/Unit/Ai/FakeProviderTest.php` (4) — déterminisme, structured conforme, closure.
- `tests/Unit/Ai/AiProviderManagerTest.php` (5) — driver par défaut depuis config (**preuve
  « changer `AI_PROVIDER` change le driver sans toucher au métier »**), driver inconnu.
- `tests/Unit/Ai/AiCompleterTest.php` (6) — passthrough sans schéma, instruction ajoutée,
  conforme (même entouré de fences), 1ʳᵉ invalide + 2ᵉ conforme (exactement 2 appels, rappel
  présent), 2 invalides → exception, driver inconnu → `provider_not_configured`.
- `tests/Unit/Ai/OpenAiProviderTest.php` (12) — contract `Http::fake` + `preventStrayRequests`
  : URL/headers/body exacts, parsing (usage, défaut 0/0), matrice d'erreurs (429 → retry →
  succès `assertSentCount(3)` ; 429 persistant ; timeout ; unreachable ; 401 ; 400 avec détail
  provider sans la clé ; 500 ; clé absente → `assertNothingSent()`).
- `tests/Unit/Ai/AnthropicProviderTest.php` (10) — même matrice, shapes Anthropic.
- `tests/Unit/Ai/AiConfigTest.php` (6) — défauts, `enabled` suivant la clé, `flush()`.
- `tests/Unit/Workflow/Handlers/AiNodeHandlerTest.php` (21) — 5 ids depuis l'enum, pipeline
  complet par mode (schéma exact asserté sur l'AiRequest reçu, label hors liste → 2 appels puis
  `structured_output_invalid`, `path_not_found`, traduction des 6 raisons provider),
  `resolveModel` (défaut config, inconnu, fournisseur désactivé, malformé), matrice
  `validate()` FR.
- `tests/Feature/Workflows/AiWorkflowRunTest.php` (8) — run e2e `manual.trigger →
ai.classification (fake/demo) → logic.condition → data.output` via le runner réel
  (**preuve « handlers ajoutés sans modifier le runner »**), dataset des 5 modes e2e,
  `ai.summary` retiré → `unknown_type`, **sentinel** : `sk-test-sentinel` injecté en config
  avant la requête Inertia, `assertStringNotContainsString` sur la réponse (aucune clé dans les
  props).

Frontend — 2 nouvelles specs (aiUsage, aiVariables) + extensions (nodeOptionLabels,
useWorkflowBuilder) : **102 tests unitaires verts** (14 nouveaux).

Suites finales : `php artisan test --compact` **550/550, 1 835 assertions** ;
`vendor/bin/phpstan analyse --no-progress` (Larastan niveau 7) **0 erreur** ;
`vendor/bin/pint --dirty --format agent` propre ; `npm run test:unit` **102 verts** ;
`npm run types:check` 0 ; `npm run build` OK ; `npm run check` OK (136 fichiers formatés).

### Commands Executed

- `search-docs` (Laravel Boost) : HTTP client (retry/timeout/ConnectionException/
  preventStrayRequests/fakes), `Illuminate\Support\Manager` ; lectures vendor (Laravel
  13.32.0) pour les signatures exactes (`Manager::__construct(Container)`,
  `PendingRequest::retry(...)`, `Response::json()`).
- `composer show laravel/framework` ; `php artisan config:show ai` ; `php artisan make:…`
  (`make:class|interface|enum|trait|exception|config|test --pest`, `--no-interaction`) ;
  boucles Red → Green par lot avec `vendor/bin/pest` périmètre étroit (`Sleep::fake()` pour
  les retries) ; greps anti-hardcode (`gpt-4o|claude|sk-` dans `app/` : aucun hit fonctionnel
  hors `config/ai.php`).
- Final : `vendor/bin/pint --dirty --format agent` ; `php artisan test --compact` ;
  `vendor/bin/phpstan analyse --no-progress` ; `npm run test:unit|types:check|check|build`.
  `php artisan wayfinder:generate` non requis (aucune route). Correctif formatage du rapport
  phase 5 signalé par `npm run check` : `npx vp check --fix docs/reports/phase5/report.md`.

## Key Information

### Technical Decisions

Arbitrages V1–V12 et décisions D1–D15 détaillés dans
[`.knowledge/memory/plans/phase6-implementation-plan.md`](../../.knowledge/memory/plans/phase6-implementation-plan.md) (prime sur phase6.md en cas d'écart de détail). Résumé des choix structurants :

- **Contrat `AiProvider` une méthode** (`complete(AiRequest): AiResponse`) ; la boucle
  structured output (instruction JSON, validation, 1 retry, exception) vit dans `AiCompleter`
  — les providers restent de purs traducteurs DTO ↔ HTTP (miroir `EmailSender`, phase 5) et le
  FakeProvider reste trivial.
- **Un handler `AiNodeHandler` paramétré par l'enum `AiMode`, instancié 5 fois** dans le
  registre : les 5 modes partagent ~90 % du comportement, les différences sont des données.
  Ajouter un 6ᵉ mode = 1 cas d'enum + 1 ligne d'enregistrement + 1 entrée de catalogue. Écart
  assumé au gabarit `{Type}Handler` de domain.md §3, validé par l'architecte.
- **Usage tokens = clé `usage` de l'output** (pas de champ DTO) : coule vers le front et vers
  l'interpolation `{{ <clé>.usage.prompt_tokens }}` sans toucher au runner ni à
  `NodeResult`/`NodeRunResult`.
- **Schéma simple `text|number|boolean|enum:…`** dans `AiJsonSchema` (pure, statique), partagé
  par completer et FakeProvider — un seul lieu de vérité ; le fake lit le schéma → réponses
  déterministes qui font passer les 5 modes sans aucune clé. Clés supplémentaires tolérées,
  requis strict.
- **Champ modèle composite `provider/model`** (options config-driven depuis `config/ai.php`,
  fournisseurs `enabled` uniquement, fake en tête) : pas de select fournisseur séparé (options
  dynamiques selon fournisseur = nouveau type de champ pour un gain nul) ; le défaut
  `fake/demo` garantit qu'un test-run ne dépense jamais un appel réel par accident. Les ids
  codés en dur de l'ancien catalogue (`claude-haiku`) n'étaient d'ailleurs pas des ids d'API
  valides.
- **Deux axes de retry distincts** : transport (providers, `Http::retry`, 429/5xx/
  ConnectionException, backoff fixe 500 ms, `1 + ai.retries` appels) ≠ contenu (completer,
  exactement 1 retry JSON). Pire cas borné : `(1 + ai.retries) × 2` appels.
- **Structured output par instruction + validation maison** (pas les features natives
  `response_format`/`output_config.format`, envoyé à titre instructif côté OpenAI seulement) :
  uniforme sur les 3 fournisseurs, indépendant des bêtas d'API ; bascule future transparente
  derrière `AiProvider`.
- **SDK officiels refusés** (`openai-php/client`, SDK Anthropic) : 2 dépendances composer pour
  une surface d'une méthode, `Http::fake` couvre 100 % des tests, pas de streaming/tools cette
  phase. HTTP client Laravel (conforme master §23).
- **Casse `Ai` partout** (`app/Services/Ai/`, `AiProvider`…) : conventions.md + domain.md
  priment sur la notation `AI*` de phase6.md ; DTOs dans `app/Data/Ai/` (précédent
  `app/Data/Workflow/`) ; interface sans suffixe `Interface` (précédent `NodeHandler`).
- **Extraction : champs en textarea « clé: type »** (types scalaires admis seulement), même
  arbitrage que les headers HTTP de la phase 5 : la whitelist de sauvegarde n'accepte que des
  scalaires — pas de structure de config nouvelle pour un seul node.

### Gotchas & Solutions

- **`NodeCatalog` statique memoïzé** traverse les tests d'un même process : tout changement de
  `config('ai.providers…')` doit être suivi de `NodeCatalog::flush()` (et d'une restauration
  en try/finally) — sinon le cache sert les définitions de la première lecture. Pour les
  workers long-lived (phase 7) : la config est relue à la construction du process.
- **`Http::retry()` Laravel 13** : la forme `retry(attempts, [500, 1500], when:)` du croquis
  n'existe pas (l'array en 1ᵉʳ arg fixe total = count+1) — forme `retry($retries + 1, 500,
when: …)` retenue ; `Sleep::fake()` dans les tests pour zéro attente réelle. Les retries
  consomment plusieurs réponses faked → séries + `assertSentCount`, jamais un seul
  `Http::response` naïf.
- **`Manager::getDefaultDriver()` est public** en Laravel 13 (override public) ; fallback
  `?? 'fake'` explicite (une config à `null` ne déclenche pas le défaut de `get()`).
- **Arrow fn + closure imbriquée + `use (&…)`** : une closure définie dans une arrow fn perd
  la liaison par référence (capture par valeur) — helpers de test en closures nommées
  `function () use (&…)` avec commentaire.
- **Fences markdown** : `AiJsonSchema::decode()` ne les retire que si le texte entier est
  enveloppé — cohérent avec le rappel du completer (« sans aucun texte avant ni après »).
- **JSON des props Inertia échappe les slashes** (`fake\/demo`) : assertions sentinel sur la
  forme échappée.
- **Générateurs artisan préfixés** (`make:exception|enum|trait`) écrivent sous
  `app/Exceptions|Enums|Concerns` : fichiers déplacés aux emplacements du plan, dossiers vides
  créés par les générateurs supprimés.
- **Budget 5 s du runner vs appels IA** : le timeout est vérifié ENTRE les nodes — un appel IA
  de 30 s complète mais coupe le node SUIVANT (raison `timeout`). Accepté et documenté ; ne
  PAS baisser `ai.timeout` sous 30 s pour « tenir dans le budget » — le vrai correctif est
  l'async (phase 7).
- **PHP 8.4** : pas de `new` dans les constantes/initializers statiques (catalogue memoïzé
  lazy conservé) ; `use Throwable;` sans namespace → warning de suite PHPUnit.

### Commands & Config

`config/ai.php` (défauts) : `default_provider` = `env('AI_PROVIDER', 'fake')` ;
`timeout` = 30 s ; `retries` = 2 ; `max_tokens` = 2048 ; `temperature` = 0.7 ;
`providers.fake` (enabled, `models: ['demo']`) ; `providers.openai` (enabled = présence de
`OPENAI_API_KEY`, `base_url` = `https://api.openai.com/v1`, `models: ['gpt-4o-mini',
'gpt-4o']`) ; `providers.anthropic` (enabled = présence de `ANTHROPIC_API_KEY`,
`base_url` = `https://api.anthropic.com`, `version` = `2023-06-01`, `models:
['claude-haiku-4-5', 'claude-sonnet-5', 'claude-opus-5']`).

Variables `.env.example` ajoutées : `AI_PROVIDER`, `AI_TIMEOUT`, `AI_RETRIES`,
`AI_MAX_TOKENS`, `AI_TEMPERATURE` ; clés fournisseurs commentées (`OPENAI_API_KEY`,
`ANTHROPIC_API_KEY`) — optionnelles (`OPENAI_BASE_URL`, `ANTHROPIC_BASE_URL`,
`ANTHROPIC_VERSION` supportés en config). Sans clé, le fournisseur n'apparaît pas dans le
builder et toute requête échoue en `provider_not_configured` avant tout appel HTTP.

### Version Notes

- Laravel 13.32.0 : `Manager::getDefaultDriver()` public abstrait ;
  `PendingRequest::retry(array|int $times, Closure|int $sleep = 0, ?callable $when, bool
$throw)` (l'array de backoffs va en 1ᵉʳ argument) ; `Http::preventStrayRequests()` pour
  l'interdiction de réseau en test ; `Http::fakeSeries`/réponses en file pour les retries.
- API OpenAI : forme actuelle `max_completion_tokens` (pas `max_tokens`, déprécié sur les
  modèles récents) ; `response_format: {type: 'json_object'}` instructif (non garanti).
- API Anthropic : `max_tokens` obligatoire dans le body ; `system` hors du tableau `messages` ;
  header `anthropic-version: 2023-06-01` ; usage `input_tokens`/`output_tokens`.

## Future Ideas (Not Planned)

- **Streaming des réponses (SSE)** — UX du builder (réponse progressive dans le tiroir de
  test) ; réévaluer le choix HTTP client vs SDK à ce moment. À évaluer avec la phase 7 (async),
  qui supprimera le blocage synchrone.
- **Function calling / tools dans les nodes AI** — principale valeur des SDK officiels ;
  réévaluer V9 quand un cas d'usage réel apparaît.
- **Features natives structured output** (`output_config.format` Anthropic) — bascule interne
  derrière `AiProvider`, quand la validation maison montrera ses limites.
- **Cache des réponses IA + budgets de tokens par équipe** — économie de coûts ; la phase 8
  (persistance des exécutions) fournira la donnée d'usage nécessaire.
- **Embeddings / RAG, providers locaux (Ollama), images/audio** — à chaque fois qu'un node en
  aura l'usage (même règle que U2, phase 5).

## Known Limitations

- **Exécution synchrone** : un node AI rallonge le test-run de la durée de l'appel (jusqu'à
  `ai.timeout`) ; le budget 5 s du runner coupe le node SUIVANT, pas le node en cours (async =
  phase 7).
- **Un seul fournisseur par node** (champ composite) ; pas de fallback automatique vers un 2ᵉ
  fournisseur.
- `usage` est une clé de sortie réservée des nodes AI (un champ d'extraction nommé `usage`
  serait écrasé ; la validation de clé `^[A-Za-z0-9_]+$` ne l'interdit pas — documenté).
- Graphes stockés avec `ai.summary` avant la phase → `unknown_type` (table vide vérifiée le
  2026-09-20 : aucun impact réel).
- Pas de streaming, function calling/tools, embeddings/RAG, cache de réponses, budgets de
  tokens (voir Future Ideas).
- `trigger.schedule`, `logic.filter`, `action.message`, `action.delay` restent `handler_missing`
  (hors périmètre de la phase 6).

## Next Phase

Phase 7 — Execution & Queue : exécutions persistées/asynchrones (file d'attente), qui lèvera la
limitation synchrone ci-dessus et reliera les exécutions aux logs de la phase 8.
