<?php

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
    | These may have different permission requirements.
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
    | Custom health checks to run after file restoration.
    | These should extend the base HealthCheck class.
    |
    */
    'health_checks' => [
        // Add your custom health check classes here
        // Example: \App\HealthChecks\FileIntegrityCheck::class,
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
    |
    */
    'cleanup_temp_files' => true,

];
