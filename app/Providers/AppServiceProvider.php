<?php

namespace App\Providers;

use App\Services\Workflow\Handlers\Data\InputHandler;
use App\Services\Workflow\Handlers\Data\OutputHandler;
use App\Services\Workflow\Handlers\Data\TransformHandler;
use App\Services\Workflow\Handlers\Logic\ConditionHandler;
use App\Services\Workflow\Handlers\Trigger\ManualHandler;
use App\Services\Workflow\NodeHandlerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NodeHandlerRegistry::class, function (): NodeHandlerRegistry {
            $registry = new NodeHandlerRegistry;
            $registry->register($this->app->make(ManualHandler::class));
            $registry->register($this->app->make(InputHandler::class));
            $registry->register($this->app->make(TransformHandler::class));
            $registry->register($this->app->make(ConditionHandler::class));
            $registry->register($this->app->make(OutputHandler::class));

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
