<?php

namespace App\Providers;

use App\Services\Ai\AiProviderManager;
use App\Services\Workflow\Handlers\Action\EmailHandler;
use App\Services\Workflow\Handlers\Action\HttpHandler;
use App\Services\Workflow\Handlers\Ai\AiMode;
use App\Services\Workflow\Handlers\Ai\AiNodeHandler;
use App\Services\Workflow\Handlers\Data\InputHandler;
use App\Services\Workflow\Handlers\Data\OutputHandler;
use App\Services\Workflow\Handlers\Data\TransformHandler;
use App\Services\Workflow\Handlers\Logic\ConditionHandler;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;
use App\Services\Workflow\Handlers\Trigger\ScheduleHandler;
use App\Services\Workflow\Handlers\Trigger\WebhookHandler;
use App\Services\Workflow\NodeHandlerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProviderManager::class);

        $this->app->singleton(NodeHandlerRegistry::class, function (): NodeHandlerRegistry {
            $registry = new NodeHandlerRegistry;
            $registry->register($this->app->make(ManualHandler::class));
            $registry->register($this->app->make(ScheduleHandler::class));
            $registry->register($this->app->make(InputHandler::class));
            $registry->register($this->app->make(TransformHandler::class));
            $registry->register($this->app->make(ConditionHandler::class));
            $registry->register($this->app->make(OutputHandler::class));
            $registry->register($this->app->make(HttpHandler::class));
            $registry->register($this->app->make(EmailHandler::class));
            $registry->register($this->app->make(WebhookHandler::class));

            foreach (AiMode::cases() as $mode) {
                $registry->register($this->app->make(AiNodeHandler::class, ['mode' => $mode]));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters (D5: the webhook key hashes the route
     * token — the raw token never becomes a cache key).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(
            (int) config('workflows.webhook.rate_limit_per_minute', 60),
        )->by('webhook:'.hash('sha256', (string) $request->route('token'))));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
