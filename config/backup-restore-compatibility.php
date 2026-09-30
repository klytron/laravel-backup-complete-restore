<?php

declare(strict_types=1);

use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables;
use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords;
use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist;

return [
    /*
    |--------------------------------------------------------------------------
    | Health Checks (wnx/laravel-backup-restore compatibility)
    |--------------------------------------------------------------------------
    |
    | This configuration is automatically merged into Laravel's config system
    | under 'backup-restore' for wnx/laravel-backup-restore compatibility.
    | For primary configuration, see config/backup-complete-restore.php.
    |
    */
    'health-checks' => [
        DatabaseHasTables::class,
        // DatabaseHasRecords::class,
        // FilesExist::class,
    ],
];