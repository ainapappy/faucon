<?php

use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\FakeProvider;
use App\Services\Ai\Providers\OpenAiProvider;

test('the default driver follows the ai.default_provider config', function () {
    config(['ai.default_provider' => 'fake']);

    expect(app(AiProviderManager::class)->provider())->toBeInstanceOf(FakeProvider::class);
});

test('changing the default provider config swaps the driver without touching the caller', function () {
    config(['ai.default_provider' => 'fake']);
    $manager = app(AiProviderManager::class);

    expect($manager->provider())->toBeInstanceOf(FakeProvider::class);

    config(['ai.default_provider' => 'openai']);

    expect($manager->provider())->toBeInstanceOf(OpenAiProvider::class);
});

test('an explicit driver name resolves the matching provider', function () {
    $manager = app(AiProviderManager::class);

    expect($manager->provider('fake'))->toBeInstanceOf(FakeProvider::class)
        ->and($manager->provider('openai'))->toBeInstanceOf(OpenAiProvider::class)
        ->and($manager->provider('anthropic'))->toBeInstanceOf(AnthropicProvider::class);
});

test('an unknown driver name throws an invalid argument exception', function () {
    $manager = app(AiProviderManager::class);

    $provider = fn (): mixed => $manager->provider('inconnu');

    expect($provider)->toThrow(InvalidArgumentException::class);
});

test('the default driver falls back to fake without any config', function () {
    config(['ai.default_provider' => null]);

    expect(app(AiProviderManager::class)->provider())->toBeInstanceOf(FakeProvider::class);
});
