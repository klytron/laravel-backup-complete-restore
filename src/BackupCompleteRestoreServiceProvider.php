<?php

namespace Scryba\LaravelBackupCompleteRestore;

use Illuminate\Support\ServiceProvider;
use Scryba\LaravelBackupCompleteRestore\Commands\BackupCompleteRestoreCommand;

class BackupCompleteRestoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/backup-complete-restore.php',
            'backup-complete-restore'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                BackupCompleteRestoreCommand::class,
            ]);

            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/backup-complete-restore.php' => config_path('backup-complete-restore.php'),
            ], 'backup-complete-restore-config');

            // Publish health checks
            $this->publishes([
                __DIR__.'/HealthChecks' => app_path('HealthChecks'),
            ], 'backup-complete-restore-health-checks');
        }
    }
}
