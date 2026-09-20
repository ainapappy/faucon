<?php

namespace App\Services\Ai;

use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\FakeProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use LogicException;

/**
 * Resolves one AiProvider driver from the config — nothing else.
 *
 * The default driver follows `ai.default_provider` (env AI_PROVIDER):
 * changing the env changes the driver with zero business-code change.
 * extend() stays available for a future custom driver (Manager pattern).
 */
final class AiProviderManager extends Manager
{
    /**
     * Typed entry point — `Manager::driver()` returns mixed (PHPStan level 7).
     *
     * @throws InvalidArgumentException Unknown driver (intercepted by
     *                                  AiCompleter → provider_not_configured).
     */
    public function provider(?string $name = null): AiProvider
    {
        $driver = $this->driver($name);

        return $driver instanceof AiProvider
            ? $driver
            : throw new LogicException(sprintf('Driver [%s] is not an AiProvider.', (string) $name));
    }

    public function getDefaultDriver(): string
    {
        return (string) ($this->config->get('ai.default_provider') ?? 'fake');
    }

    protected function createFakeDriver(): AiProvider
    {
        return new FakeProvider;
    }

    protected function createOpenaiDriver(): AiProvider
    {
        return new OpenAiProvider;
    }

    protected function createAnthropicDriver(): AiProvider
    {
        return new AnthropicProvider;
    }
}
