<?php

return [

    /*
    |--------------------------------------------------------------------
    | AI Provider & AI Nodes (phase 6)
    |--------------------------------------------------------------------
    */

    // Fournisseur utilisé quand un node ne précise pas de modèle (ou valeur vide).
    'default_provider' => env('AI_PROVIDER', 'fake'),

    // Timeout (secondes) d'un appel fournisseur, retries transport (429/5xx),
    // et valeurs par défaut des nodes.
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'retries' => (int) env('AI_RETRIES', 2),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
    'temperature' => (float) env('AI_TEMPERATURE', 0.7),

    /*
    | Fournisseurs. 'enabled' calcule la disponibilité (et masque le fournisseur
    | sans clé dans le builder) ; 'models' = ids d'API dans l'ordre de priorité
    | (le premier est le modèle par défaut du fournisseur). AUCUNE clé ne sort
    | jamais de la config : ni props, ni logs, ni exceptions UI.
    */
    'providers' => [
        'fake' => [
            'enabled' => true,
            'models' => ['demo', 'demo1', 'demo3', 'demo4'],
        ],
        'openai' => [
            'enabled' => filled(env('OPENAI_API_KEY')),
            'key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'models' => ['gpt-4o-mini', 'gpt-4o'],
        ],
        'anthropic' => [
            'enabled' => filled(env('ANTHROPIC_API_KEY')),
            'key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'models' => ['claude-haiku-4-5', 'claude-sonnet-5', 'claude-opus-5'],
        ],
        'zai' => [
            'enabled' => filled(env('ZAI_API_KEY')),
            'key' => env('ZAI_API_KEY'),
            'base_url' => env('ZAI_BASE_URL', 'https://api.z.ai/api/paas/v4'),
            'models' => ['glm-4.6', 'glm-4.5', 'glm-4.5-flash'],
        ],
    ],

];
