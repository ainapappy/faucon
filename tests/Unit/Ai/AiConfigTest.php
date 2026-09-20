<?php

use App\Services\Workflow\NodeCatalog;

/**
 * Read the ai config file fresh (the expressions re-evaluate env()).
 *
 * @return array<string, mixed>
 */
function freshAiConfig(): array
{
    return require config_path('ai.php');
}

/**
 * Patch the env adapters ($_ENV/$_SERVER) used by env(), restoring after.
 *
 * @param  array<string, string|null>  $values
 */
function withEnvValues(array $values, callable $callback): mixed
{
    $original = [];

    foreach ($values as $name => $value) {
        $original[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];

        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);

            continue;
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    try {
        return $callback();
    } finally {
        foreach ($original as $name => [$env, $server]) {
            if ($env !== null) {
                $_ENV[$name] = $env;
            } else {
                unset($_ENV[$name]);
            }

            if ($server !== null) {
                $_SERVER[$name] = $server;
            } else {
                unset($_SERVER[$name]);
            }
        }
    }
}

test('the fake provider is enabled without any configuration', function () {
    expect(config('ai.providers.fake.enabled'))->toBeTrue()
        ->and(config('ai.providers.fake.models'))->toBe(['demo']);
});

test('the default provider falls back to fake when AI_PROVIDER is unset', function () {
    $defaultProvider = withEnvValues(['AI_PROVIDER' => null], fn (): mixed => freshAiConfig()['default_provider']);

    expect($defaultProvider)->toBe('fake');
});

test('openai is enabled exactly when an API key is present', function () {
    [$withKey, $withoutKey] = withEnvValues(['OPENAI_API_KEY' => null], function (): array {
        $withKey = withEnvValues(['OPENAI_API_KEY' => 'sk-test-value'], fn (): mixed => freshAiConfig());
        $withoutKey = withEnvValues(['OPENAI_API_KEY' => null], fn (): mixed => freshAiConfig());

        return [$withKey, $withoutKey];
    });

    expect($withKey['providers']['openai']['enabled'])->toBeTrue()
        ->and($withoutKey['providers']['openai']['enabled'])->toBeFalse()
        ->and($withKey['providers']['openai']['models'])->toBe(['gpt-4o-mini', 'gpt-4o'])
        ->and($withKey['providers']['openai']['base_url'])->toBe('https://api.openai.com/v1');
});

test('anthropic is enabled exactly when an API key is present', function () {
    [$withKey, $withoutKey] = withEnvValues(['ANTHROPIC_API_KEY' => null], function (): array {
        $withKey = withEnvValues(['ANTHROPIC_API_KEY' => 'ak-test-value'], fn (): mixed => freshAiConfig());
        $withoutKey = withEnvValues(['ANTHROPIC_API_KEY' => null], fn (): mixed => freshAiConfig());

        return [$withKey, $withoutKey];
    });

    expect($withKey['providers']['anthropic']['enabled'])->toBeTrue()
        ->and($withoutKey['providers']['anthropic']['enabled'])->toBeFalse()
        ->and($withKey['providers']['anthropic']['models'])->toBe(['claude-haiku-4-5', 'claude-sonnet-5', 'claude-opus-5'])
        ->and($withKey['providers']['anthropic']['version'])->toBe('2023-06-01');
});

test('zai is enabled exactly when an API key is present', function () {
    [$withKey, $withoutKey] = withEnvValues(['ZAI_API_KEY' => null], function (): array {
        $withKey = withEnvValues(['ZAI_API_KEY' => 'zai-test-value'], fn (): mixed => freshAiConfig());
        $withoutKey = withEnvValues(['ZAI_API_KEY' => null], fn (): mixed => freshAiConfig());

        return [$withKey, $withoutKey];
    });

    expect($withKey['providers']['zai']['enabled'])->toBeTrue()
        ->and($withoutKey['providers']['zai']['enabled'])->toBeFalse()
        ->and($withKey['providers']['zai']['models'])->toBe(['glm-4.6', 'glm-4.5', 'glm-4.5-flash'])
        ->and($withKey['providers']['zai']['base_url'])->toBe('https://api.z.ai/api/paas/v4');
});

test('catalog definitions are memoized between reads', function () {
    expect(NodeCatalog::definitionFor('ai.classification'))->toBe(NodeCatalog::definitionFor('ai.classification'));
});

test('flush rebuilds the memoized catalog definitions', function () {
    $before = NodeCatalog::definitionFor('ai.classification');

    NodeCatalog::flush();

    expect($before)->not->toBeNull()
        ->and(NodeCatalog::definitionFor('ai.classification'))->not->toBe($before);
});
