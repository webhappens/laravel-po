<?php

namespace WebHappens\LaravelPo;

use Illuminate\Support\ServiceProvider;
use WebHappens\LaravelPo\Commands\ExportCommand;
use WebHappens\LaravelPo\Commands\ImportCommand;
use WebHappens\LaravelPo\Commands\PoeditorDownloadCommand;

class PoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/po.php',
            'po'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/po.php' => config_path('po.php'),
            ], 'po-config');

            $this->commands([
                ExportCommand::class,
                ImportCommand::class,
                PoeditorDownloadCommand::class,
            ]);
        }
    }
}
