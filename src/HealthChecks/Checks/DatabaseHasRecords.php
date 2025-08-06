<?php

declare(strict_types=1);

namespace Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks;

use Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasRecords as WnxDatabaseHasRecords;

/**
 * Health check to verify that the database has records after restoration.
 * 
 * This class extends the Wnx Laravel Backup Restore DatabaseHasRecords check
 * to provide the same functionality while being part of our package.
 * 
 * @package Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks
 */
class DatabaseHasRecords extends WnxDatabaseHasRecords
{
    /**
     * Get the name of the health check.
     */
    public function name(): string
    {
        return 'Database Has Records';
    }

    /**
     * Get the description of the health check.
     */
    public function description(): string
    {
        return 'Verifies that the restored database contains records in tables.';
    }
} 