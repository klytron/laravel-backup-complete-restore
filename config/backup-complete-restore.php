<?php

declare(strict_types=1);

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
    | This is typically the full path to the Laravel installation in the container.
    | Common values: 'var/www/html', '/app', '/var/www'
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
    | This provides a safety net in case the restore process needs to be reversed.
    |
    */
    'backup_existing_files' => true,

    /*
    |--------------------------------------------------------------------------
    | File Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions to set on restored files and directories.
    | These should be appropriate for your web server and security requirements.
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
    | These may have different permission requirements than storage directories.
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
    | These are typically more restrictive in terms of permissions.
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
    | Health checks are run after a given backup has been restored.
    | With health checks, you can make sure that the restored database contains 
    | the data you expect and that critical files are in place.
    |
    | You can add your own health checks by adding a class that extends the 
    | HealthCheck class. The restore command will fail if any health checks fail.
    |
    | Available built-in health checks:
    | - \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class
    | - \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords::class
    | - \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class
    |
    */
    'health_checks' => [
        // Add your custom health check classes here
        // Example: \App\HealthChecks\FileIntegrityCheck::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration Overrides
    |--------------------------------------------------------------------------
    |
    | Database restoration options that extend Laravel's existing database configuration.
    | The actual database connections are read from config/database.php.
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
    | These settings control how the restoration process operates.
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
    | These settings control how errors are handled and logged.
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
    | These settings help ensure the security and integrity of the restoration.
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

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    |
    | Base path for temporary extraction directory.
    | A timestamp will be appended to make it unique.
    |
    */
    'temp_directory' => storage_path('app/temp-restore'),

    /*
    |--------------------------------------------------------------------------
    | Cleanup Temporary Files
    |--------------------------------------------------------------------------
    |
    | Whether to automatically clean up temporary files after restoration.
    | Set to false if you want to inspect the extracted files for debugging.
    |
    */
    'cleanup_temp_files' => true,

];
