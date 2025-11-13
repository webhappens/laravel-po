<?php

namespace WebHappens\LaravelPoSync;

use Illuminate\Support\ServiceProvider;
use WebHappens\LaravelPoSync\Commands\ExportCommand;
use WebHappens\LaravelPoSync\Commands\ImportCommand;
use WebHappens\LaravelPoSync\Commands\PoeditorDownloadCommand;

class PoSyncServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/po-sync.php',
            'po-sync'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/po-sync.php' => config_path('po-sync.php'),
            ], 'po-sync-config');

            $this->commands([
                ExportCommand::class,
                ImportCommand::class,
                PoeditorDownloadCommand::class,
            ]);
        }
    }
}
