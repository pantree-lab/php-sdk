<?php

namespace Pantree\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class PantreeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/pantree.php', 'pantree');
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes(
            [__DIR__ . '/../../config/pantree.php' => config_path('pantree.php')],
            'pantree-config',
        );

        // Register exception handler — report unhandled exceptions automatically
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->reportable(function (\Throwable $e) {
                try {
                    Pantree::captureException($e, [
                        'runtime'     => 'laravel',
                        'environment' => config('pantree.environment', app()->environment()),
                        'context'     => [
                            'laravel_version' => app()->version(),
                            'php_version'     => PHP_VERSION,
                        ],
                    ]);
                } catch (\Throwable) {
                    // Never let Pantree crash the app
                }
            });

        // Schedule health reports every 30 minutes (if enabled)
        if (config('pantree.health_reporting', false)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->call(fn () => Pantree::sendHealthReport())
                    ->everyThirtyMinutes()
                    ->name('pantree:health-report')
                    ->withoutOverlapping();
            });
        }
    }
}
