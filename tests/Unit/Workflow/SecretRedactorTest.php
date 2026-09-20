<?php

use App\Services\Workflow\Log\SecretRedactor;

/**
 * The test config mirrors the production defaults of config/workflows.php.
 *
 * @param  array<string, mixed>  $overrides
 */
function redactorConfig(array $overrides = []): void
{
    config(['workflows.logs' => array_merge([
        'retention_days' => 30,
        'max_string_chars' => 2000,
        'max_json_bytes' => 65536,
        'redacted_keys' => ['token', 'api_key', 'secret', 'password'],
        'redacted_key_suffixes' => ['_token', '_secret', '_api_key'],
    ], $overrides)]);
}

/**
 * Whether the needle exists anywhere in the nested structure.
 */
function containsRecursively(mixed $haystack, mixed $needle): bool
{
    if ($haystack === $needle) {
        return true;
    }

    if (! is_array($haystack)) {
        return false;
    }

    foreach ($haystack as $value) {
        if (containsRecursively($value, $needle)) {
            return true;
        }
    }

    return false;
}

test('exact keys are masked case-insensitively', function () {
    redactorConfig(['redacted_keys' => ['token']]);

    $redacted = app(SecretRedactor::class)->redact([
        'Token' => 'abc',
        'token' => 'def',
        'TOKEN' => 'ghi',
        'other' => 'ok',
    ]);

    expect($redacted)->toBe([
        'Token' => '[masqué]',
        'token' => '[masqué]',
        'TOKEN' => '[masqué]',
        'other' => 'ok',
    ]);
});

test('suffix matches catch derived key names', function () {
    redactorConfig();

    $redacted = app(SecretRedactor::class)->redact([
        'stripe_api_key' => 'sk_live_1',
        'user' => ['webhook_token' => 't'],
    ]);

    expect($redacted['stripe_api_key'])->toBe('[masqué]')
        ->and($redacted['user']['webhook_token'])->toBe('[masqué]');
});

test('structure and lists are preserved', function () {
    redactorConfig(['redacted_keys' => ['password']]);

    $value = [
        'a' => [['password' => 'x', 'n' => 1], ['password' => 'y']],
        'b' => ['c' => true, 'd' => null],
    ];

    expect(app(SecretRedactor::class)->redact($value))->toBe([
        'a' => [['password' => '[masqué]', 'n' => 1], ['password' => '[masqué]']],
        'b' => ['c' => true, 'd' => null],
    ]);
});

test('long strings are truncated with an ellipsis', function () {
    redactorConfig(['max_string_chars' => 10]);

    $redactor = app(SecretRedactor::class);

    expect($redactor->redact('abcdefghijklmnop'))->toBe('abcdefghij…')
        ->and($redactor->redact('0123456789'))->toBe('0123456789');
});

test('non-string scalars are left untouched', function () {
    redactorConfig(['max_string_chars' => 1]);

    expect(app(SecretRedactor::class)->redact([
        'i' => 42,
        'f' => 1.5,
        'b' => false,
        'n' => null,
    ]))->toBe(['i' => 42, 'f' => 1.5, 'b' => false, 'n' => null]);
});

test('an empty config masks and truncates nothing', function () {
    redactorConfig(['redacted_keys' => [], 'redacted_key_suffixes' => []]);

    expect(app(SecretRedactor::class)->redact(['token' => 'abc']))->toBe(['token' => 'abc']);
});

test('the config is re-read on every call', function () {
    redactorConfig(['redacted_keys' => []]);

    $redactor = app(SecretRedactor::class);

    expect($redactor->redact(['token' => 'abc']))->toBe(['token' => 'abc']);

    redactorConfig(['redacted_keys' => ['token']]);

    expect($redactor->redact(['token' => 'abc']))->toBe(['token' => '[masqué]']);
});

test('a branch beyond the depth cap becomes a marker', function () {
    redactorConfig();

    $deep = [];
    $cursor = &$deep;

    for ($i = 0; $i < 60; $i++) {
        $cursor['k'] = [];
        $cursor = &$cursor['k'];
    }

    $cursor['leaf'] = 'v';
    unset($cursor);

    $redacted = app(SecretRedactor::class)->redact($deep);

    expect(containsRecursively($redacted, '[profondeur max]'))->toBeTrue()
        ->and(containsRecursively($redacted, 'v'))->toBeFalse();
});
