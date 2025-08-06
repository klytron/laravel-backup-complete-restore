# Configuration Guide

## Overview

This package uses a consolidated configuration approach that leverages your existing Laravel configuration files (`config/database.php`, `config/filesystems.php`, `config/backup.php`) instead of redefining settings. This ensures consistency and reduces configuration duplication.

## Configuration File

The main configuration file is `config/backup-complete-restore.php`. This file contains only restoration-specific options and overrides, while reading core settings from your existing Laravel configuration.

## Configuration Sections

### 1. File Mappings

Define how files from the backup should be mapped to your local filesystem:

```php
'file_mappings' => [
    // Map backup paths to local paths
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/storage/logs' => storage_path('logs'),
    'var/www/html/storage/framework/cache' => storage_path('framework/cache'),
    'var/www/html/storage/framework/sessions' => storage_path('framework/sessions'),
    'var/www/html/storage/framework/views' => storage_path('framework/views'),
    'var/www/html/public' => public_path(),
    'var/www/html/app' => base_path('app'),
    'var/www/html/config' => base_path('config'),
    'var/www/html/database' => base_path('database'),
    'var/www/html/resources' => base_path('resources'),
    'var/www/html/routes' => base_path('routes'),
],
```

### 2. Container Base Path

Specify the base path used in Spatie backup containers (usually Docker container paths):

```php
'container_base_path' => 'var/www/html',
```

### 3. Database Configuration Overrides

Database restoration options that extend Laravel's existing database configuration:

```php
'database' => [
    'restore_options' => [
        // Whether to drop all tables before restoring
        'drop_tables_before_restore' => false,
        
        // Skip foreign key checks during restore
        'skip_foreign_key_checks' => true,
        
        // Use single transaction for restore
        'use_single_transaction' => true,
        
        // Maximum execution time for database restore (seconds)
        'max_execution_time' => 300,
        
        // Whether to show progress during restore
        'show_progress' => true,
    ],
],
```

### 4. File Restoration Options

Configure file restoration behavior:

```php
'files' => [
    'restore_options' => [
        // Whether to backup existing files before overwriting
        'backup_existing_files' => true,
        
        // Directory to store backups of existing files
        'backup_directory' => storage_path('app/backup-temp'),
        
        // File permissions to set after restoration
        'file_permissions' => 0644,
        
        // Directory permissions to set after restoration
        'directory_permissions' => 0755,
        
        // Whether to preserve file timestamps
        'preserve_timestamps' => true,
        
        // Whether to show progress during file restore
        'show_progress' => true,
    ],
],
```

### 5. Backup Sources

Configure backup source disks and settings:

```php
'backup_sources' => [
    'restore_options' => [
        // Default disk to use for backup sources
        'default_disk' => 'local',
        
        // Backup directory on the disk
        'backup_directory' => 'laravel-backup',
        
        // Whether to list backups from all configured disks
        'list_all_disks' => false,
        
        // Maximum number of backups to show in list
        'max_backups_to_show' => 20,
    ],
],
```

### 6. Restoration Process

Configure the overall restoration process:

```php
'restoration' => [
    // Whether to run health checks after restoration
    'run_health_checks' => true,
    
    // Whether to clean up temporary files after restoration
    'cleanup_temp_files' => true,
    
    // Whether to show detailed progress information
    'show_detailed_progress' => true,
    
    // Maximum time to wait for restoration to complete (seconds)
    'max_execution_time' => 1800,
],
```

### 7. Error Handling

Configure error handling and logging:

```php
'error_handling' => [
    // Whether to log errors to Laravel's log system
    'log_errors' => true,
    
    // Log level for restoration errors
    'log_level' => 'error',
    
    // Whether to continue on non-critical errors
    'continue_on_errors' => false,
    
    // Whether to show stack traces for errors
    'show_stack_traces' => false,
],
```

### 8. Security

Configure security-related options:

```php
'security' => [
    // Whether to require confirmation for destructive operations
    'require_confirmation' => true,
    
    // Whether to validate file paths before restoration
    'validate_file_paths' => true,
    
    // Allowed file extensions for restoration
    'allowed_file_extensions' => [
        'php', 'js', 'css', 'html', 'txt', 'json', 'xml', 'yml', 'yaml',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'pdf', 'doc', 'docx',
        'xls', 'xlsx', 'zip', 'tar', 'gz', 'sql',
    ],
    
    // Whether to check file integrity after restoration
    'check_file_integrity' => true,
],
```

## Health Checks Configuration

Configure health checks that run after restoration:

```php
'health_checks' => [
    // Basic database health checks
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
    
    // Check if database has records (optional)
    // \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords::class,
    
    // Check if critical files exist after restoration
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
        'files' => [
            storage_path('app/public'),
            storage_path('logs'),
            storage_path('framework/cache'),
        ],
    ],
],
```

## Integration with Existing Laravel Configuration

This package automatically reads from your existing Laravel configuration:

### Database Connections
- Reads from `config/database.php`
- Uses the same connection settings as your application
- No need to redefine database configurations

### File Systems
- Reads from `config/filesystems.php`
- Uses the same disk configurations as your application
- Supports all Laravel storage drivers (local, S3, Google Drive, etc.)

### Backup Sources
- Reads from `config/backup.php`
- Uses the same backup configuration as Spatie Laravel Backup
- Maintains consistency with your backup setup

## Publishing Configuration

To customize the configuration:

```bash
php artisan vendor:publish --provider="Scryba\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider"
```

This will create `config/backup-complete-restore.php` in your application.

## Environment Variables

You can use environment variables in your configuration:

```php
'container_base_path' => env('BACKUP_CONTAINER_BASE_PATH', 'var/www/html'),
'file_mappings' => [
    env('BACKUP_STORAGE_PATH', 'var/www/html/storage/app/public') => storage_path('app/public'),
],
```

## Next Steps

- [Learn about command options](./commands.md)
- [Set up health checks](./health-checks.md)
- [Understand file mappings](./file-mappings.md)
- [Troubleshooting](./troubleshooting.md) 