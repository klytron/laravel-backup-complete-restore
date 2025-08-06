<?php

declare(strict_types=1);

namespace Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks;

use Wnx\LaravelBackupRestore\HealthChecks\Checks\FilesExist as WnxFilesExist;

/**
 * Health check to verify that critical files exist after restoration.
 * 
 * This class extends the Wnx Laravel Backup Restore FilesExist check
 * to provide the same functionality while being part of our package.
 * 
 * @package Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks
 */
class FilesExist extends WnxFilesExist
{
    /**
     * Get the name of the health check.
     */
    public function name(): string
    {
        return 'Files Exist';
    }

    /**
     * Get the description of the health check.
     */
    public function description(): string
    {
        return 'Verifies that critical files exist after restoration.';
    }
} 