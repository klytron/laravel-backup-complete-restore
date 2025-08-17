<?php

declare(strict_types=1);

namespace Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks;

use Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables as WnxDatabaseHasTables;

/**
 * Health check to verify that the database has tables after restoration.
 * 
 * This class extends the Wnx Laravel Backup Restore DatabaseHasTables check
 * to provide the same functionality while being part of our package.
 * 
 * @package Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks
 */
class DatabaseHasTables extends WnxDatabaseHasTables
{
    /**
     * Get the name of the health check.
     */
    public function name(): string
    {
        return 'Database Has Tables';
    }

    /**
     * Get the description of the health check.
     */
    public function description(): string
    {
        return 'Verifies that the restored database contains tables.';
    }
} 