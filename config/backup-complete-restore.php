<?php

declare(strict_types=1);

use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables;
use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords;
use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist;

return [

    /*
    |--------------------------------------------------------------------------
    | File Restoration Mappings
    |--------------------------------------------------------------------------
    |
    | Define how files from the backup should be mapped to local paths.
    | The key is the path in the backup (relative to the container base path),
    | and the value is the local path where files should be restored.
    |
    | This is essential for correctly mapping files from container paths
    | (e.g., /var/www/html/public/uploads) to local paths (e.g., public/uploads).
    |
    */
    'file_mappings' => [
        'public/uploads' => public_path('uploads'),
        'public/download' => public_path('download'),
        'storage/app' => storage_path('app'),
        'storage/plugins' => storage_path('plugins'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Container Base Path
    |--------------------------------------------------------------------------
    |
    | The base path used in Spatie Laravel Backup containers.
    | Common values: 'var/www/html', 'app', 'var/www'
    |
    */
    'container_base_path' => 'var/www/html',

    /*
    |--------------------------------------------------------------------------
    | Backup Existing Files
    |--------------------------------------------------------------------------
    |
    | Whether to create backups of existing files before restoring.
    | If true, existing directories will be backed up with a timestamp suffix.
    |
    */
    'backup_existing_files' => true,

    /*
    |--------------------------------------------------------------------------
    | File Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions to set on restored files and directories.
    |
    */
    'permissions' => [
        'directories' => 0755,
        'files' => 0644,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Accessible Directories
    |--------------------------------------------------------------------------
    |
    | Directories that should be web-accessible (typically in public/).
    |
    */
    'web_directories' => [
        'public/uploads',
        'public/download',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Directories
    |--------------------------------------------------------------------------
    |
    | Private storage directories that should not be web-accessible.
    |
    */
    'storage_directories' => [
        'storage/app',
        'storage/plugins',
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Health checks run after a backup has been restored to ensure the database
    | and critical files are intact.
    |
    */
    'health_checks' => [
        DatabaseHasTables::class,
        // DatabaseHasRecords::class,
        // FilesExist::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Restoration Process Options
    |--------------------------------------------------------------------------
    |
    | Settings controlling resource limits and post-restoration actions.
    |
    */
    'restoration' => [
        /*
         * Maximum execution time for restoration in seconds (default 3600 / 1 hour).
         */
        'max_execution_time' => 3600,

        /*
         * Memory limit for restoration process (default '512M').
         */
        'memory_limit' => '512M',

        /*
         * Whether to automatically run health checks after restoration.
         */
        'run_health_checks' => true,

        /*
         * Whether to automatically clear application and config caches after restore.
         */
        'clear_caches' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    |
    | Base path for temporary extraction directory.
    | A unique timestamp suffix will be appended automatically.
    |
    */
    'temp_directory' => storage_path('app/temp-restore'),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Temporary Files
    |--------------------------------------------------------------------------
    |
    | Whether to automatically clean up temporary files after restoration.
    |
    */
    'cleanup_temp_files' => true,

];
