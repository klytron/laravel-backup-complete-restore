<?php

declare(strict_types=1);

use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables;
use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords;
use Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist;

return [
    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Health checks are run after a given backup has been restored.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    | For full configuration options, see config/backup-complete-restore.php
    |
    */
    'health-checks' => [
        // Basic database health checks
        DatabaseHasTables::class,
        
        // Check if database has records (optional - can be commented out if you have empty tables)
        // DatabaseHasRecords::class,
        
        // Check if critical files exist after restoration
        FilesExist::class => [
            'files' => [
                storage_path('app/public'),
                storage_path('logs'),
                storage_path('framework/cache'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration Overrides
    |--------------------------------------------------------------------------
    |
    | Database restoration options that extend Laravel's existing database configuration.
    | The actual database connections are read from config/database.php.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    |
    */
    'database' => [
        /*
         * Database restoration options
         */
        'restore_options' => [
            /*
             * Whether to drop all tables before restoring
             * Default: false (safer option)
             */
            'drop_tables_before_restore' => false,

            /*
             * Whether to skip foreign key checks during restore
             * Default: true (helps with restore order issues)
             */
            'skip_foreign_key_checks' => true,

            /*
             * Whether to use single transaction for restore
             * Default: true (ensures atomic restore)
             */
            'use_single_transaction' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Restoration Options
    |--------------------------------------------------------------------------
    |
    | File restoration options that extend Laravel's existing filesystem configuration.
    | The actual file paths and disks are read from config/filesystems.php and config/backup.php.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    |
    */
    'files' => [
        /*
         * File restoration options
         */
        'restore_options' => [
            /*
             * Whether to overwrite existing files
             * Default: true (restore will overwrite existing files)
             */
            'overwrite_existing' => true,

            /*
             * Whether to preserve file permissions
             * Default: true (maintains original file permissions)
             */
            'preserve_permissions' => true,

            /*
             * Whether to create directories if they don't exist
             * Default: true (ensures all directories are created)
             */
            'create_directories' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Source Configuration Overrides
    |--------------------------------------------------------------------------
    |
    | Backup source configuration that extends Laravel's existing backup configuration.
    | The actual backup settings are read from config/backup.php.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    |
    */
    'backup_sources' => [
        /*
         * Backup naming pattern override
         * This can override the pattern from config/backup.php if needed
         */
        'filename_pattern_override' => null, // Set to override, null to use config/backup.php

        /*
         * Backup file extension
         */
        'file_extension' => '.zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Restoration Process Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the restoration process behavior.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    |
    */
    'restoration' => [
        /*
         * Whether to show progress during restoration
         * Default: true (shows progress bars and status)
         */
        'show_progress' => true,

        /*
         * Whether to validate backup before restoration
         * Default: true (ensures backup integrity)
         */
        'validate_backup' => true,

        /*
         * Whether to create a backup before restoration (safety measure)
         * Default: true (creates a backup of current state before restoring)
         */
        'create_safety_backup' => true,

        /*
         * Maximum execution time for restoration (in seconds)
         * Default: 3600 (1 hour)
         */
        'max_execution_time' => 3600,

        /*
         * Memory limit for restoration process
         * Default: '512M'
         */
        'memory_limit' => '512M',

        /*
         * Whether to run health checks after restoration
         * Default: true (ensures restoration was successful)
         */
        'run_health_checks' => true,

        /*
         * Whether to clear caches after restoration
         * Default: true (ensures fresh cache after restore)
         */
        'clear_caches' => true,

        /*
         * Whether to optimize application after restoration
         * Default: true (runs artisan optimize)
         */
        'optimize_application' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling and Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for error handling and logging during restoration.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    |
    */
    'error_handling' => [
        /*
         * Whether to log restoration attempts
         * Default: true (logs all restoration activities)
         */
        'log_restorations' => true,

        /*
         * Log level for restoration logs
         * Default: 'info'
         */
        'log_level' => 'info',

        /*
         * Whether to send notifications on restoration failure
         * Default: true (uses your backup notification system)
         */
        'notify_on_failure' => true,

        /*
         * Whether to send notifications on restoration success
         * Default: true (uses your backup notification system)
         */
        'notify_on_success' => true,

        /*
         * Maximum number of retry attempts for failed restorations
         * Default: 3
         */
        'max_retry_attempts' => 3,

        /*
         * Delay between retry attempts (in seconds)
         * Default: 60
         */
        'retry_delay' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security and Validation
    |--------------------------------------------------------------------------
    |
    | Security and validation settings for the restoration process.
    | This configuration is automatically merged into Laravel's config system
    | for wnx/laravel-backup-restore compatibility.
    |
    */
    'security' => [
        /*
         * Whether to require confirmation for destructive operations
         * Default: true (requires user confirmation)
         */
        'require_confirmation' => true,

        /*
         * Whether to validate backup file integrity
         * Default: true (checks backup file integrity)
         */
        'validate_integrity' => true,

        /*
         * Whether to check backup file permissions
         * Default: true (ensures proper file permissions)
         */
        'check_permissions' => true,

        /*
         * Allowed file extensions for backup files
         * Default: ['zip', 'tar.gz', 'tar']
         */
        'allowed_extensions' => ['zip', 'tar.gz', 'tar'],

        /*
         * Maximum backup file size (in bytes)
         * Default: 1073741824 (1GB)
         */
        'max_file_size' => 1073741824,
    ],
]; 