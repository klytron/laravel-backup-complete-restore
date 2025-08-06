<?php

namespace Scryba\LaravelBackupCompleteRestore;

use Illuminate\Support\ServiceProvider;
use Scryba\LaravelBackupCompleteRestore\Commands\BackupCompleteRestoreCommand;
use Scryba\LaravelBackupCompleteRestore\Commands\ScrybaBackupHealthCheckCommand;

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

        // Merge configuration for wnx/laravel-backup-restore compatibility
        $this->mergeConfigFrom(
            __DIR__.'/../config/backup-restore-compatibility.php',
            'backup-restore'
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
                ScrybaBackupHealthCheckCommand::class,
            ]);

            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/backup-complete-restore.php' => config_path('backup-complete-restore.php'),
            ], 'backup-complete-restore-config');

            // Publish health checks
            $this->publishes([
                __DIR__.'/HealthChecks' => app_path('HealthChecks'),
            ], 'backup-complete-restore-health-checks');

            // Note: No need to publish compatibility configuration as it's automatically merged
            // into Laravel's config system for wnx/laravel-backup-restore compatibility
        }

        // Note: Configuration is automatically merged into Laravel's config system
        // for wnx/laravel-backup-restore compatibility - no physical file needed
    }
}
