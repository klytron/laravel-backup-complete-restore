# Laravel Backup Complete Restore

A comprehensive Laravel package for complete backup restoration, including both database and file restoration from **Spatie Laravel Backup** archives.

## Prerequisites

This package is designed to work with [Spatie Laravel Backup](https://github.com/spatie/laravel-backup) and requires it to be installed and configured in your application.

### Required Dependencies

1. **Spatie Laravel Backup**: The foundation backup package that creates the archives this package restores from
   ```bash
   composer require spatie/laravel-backup
   ```

2. **Scryba Laravel Backup Complete Restore**: This package for restoration
   ```bash
   composer require scryba/laravel-backup-complete-restore
   ```

### Spatie Laravel Backup Configuration

Before using this package, ensure Spatie Laravel Backup is properly configured:

1. **Publish Spatie configuration**:
   ```bash
   php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
   ```

2. **Configure backup settings** in `config/backup.php`:
   ```php
   'backup' => [
       'name' => env('APP_NAME', 'laravel-backup'),
       'source' => [
           'files' => [
               'include' => [
                   storage_path(),
               ],
               'exclude' => [
                   storage_path('app/laravel-backup'),
                   storage_path('app/backup-temp'),
               ],
           ],
           'databases' => [
               'mysql',
           ],
       ],
       'destination' => [
           'disks' => [
               'local',
               'google',
           ],
       ],
   ],
   ```

3. **Set up storage disks** in `config/filesystems.php` for your backup destinations

## Features

- **Complete Restoration**: Restore both database and files from a single backup archive
- **File Mapping**: Intelligent mapping of container paths to local paths
- **Health Checks**: Comprehensive health checks after restoration
- **Safety Features**: Backup existing files before restoration
- **Permission Management**: Automatic permission setting for restored files
- **Multiple Disk Support**: Restore from local, Google Drive, or other disks
- **Progress Tracking**: Real-time progress indicators during restoration
- **Error Handling**: Comprehensive error handling and logging
- **Security**: Built-in security validations and confirmations
- **Spatie Integration**: Seamlessly works with Spatie Laravel Backup archives

## Installation

```bash
composer require scryba/laravel-backup-complete-restore
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Scryba\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider"
```

### Compatibility with wnx/laravel-backup-restore

This package automatically handles compatibility with the `wnx/laravel-backup-restore` dependency by:

1. **Creating Internal Classes**: Provides internal health check classes that extend the dependency classes
2. **Config System Integration**: Automatically merges configuration into Laravel's config system for seamless compatibility
3. **No Physical Files**: No need to create physical `config/backup-restore.php` files - everything is handled automatically

The package uses its own internal classes instead of directly depending on the external ones:

```php
// Instead of using dependency classes directly:
// use Wnx\LaravelBackupRestore\HealthChecks\Checks\DatabaseHasTables;

// Use our internal classes:
use Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables;
```

### Configuration Overview

The `config/backup-complete-restore.php` file provides configuration that **extends** Laravel's existing configuration instead of redefining it:

```php
<?php

declare(strict_types=1);

return [
    // File mappings from backup to local paths
    'file_mappings' => [
        'public/uploads' => public_path('uploads'),
        'public/download' => public_path('download'),
        'storage/app' => storage_path('app'),
        'storage/plugins' => storage_path('plugins'),
    ],

    // Container base path in backups
    'container_base_path' => 'var/www/html',

    // Whether to backup existing files before restore
    'backup_existing_files' => true,

    // File permissions
    'permissions' => [
        'directories' => 0755,
        'files' => 0644,
    ],

    // Web accessible directories
    'web_directories' => [
        'public/uploads',
        'public/download',
    ],

    // Storage directories
    'storage_directories' => [
        'storage/app',
        'storage/plugins',
    ],

    // Health checks
    'health_checks' => [
        // Add your health check classes here
    ],

    // Database restoration options (extends config/database.php)
    'database' => [
        'restore_options' => [
            'drop_tables_before_restore' => false,
            'skip_foreign_key_checks' => true,
            'use_single_transaction' => true,
        ],
    ],

    // File restoration options (extends config/filesystems.php and config/backup.php)
    'files' => [
        'restore_options' => [
            'overwrite_existing' => true,
            'preserve_permissions' => true,
            'create_directories' => true,
        ],
    ],

    // Backup source overrides (extends config/backup.php)
    'backup_sources' => [
        'filename_pattern_override' => null, // Set to override, null to use config/backup.php
        'file_extension' => '.zip',
    ],

    // Restoration process configuration
    'restoration' => [
        'show_progress' => true,
        'validate_backup' => true,
        'create_safety_backup' => true,
        'max_execution_time' => 3600,
        'memory_limit' => '512M',
        'run_health_checks' => true,
        'clear_caches' => true,
        'optimize_application' => true,
    ],

    // Error handling and logging
    'error_handling' => [
        'log_restorations' => true,
        'log_level' => 'info',
        'notify_on_failure' => true,
        'notify_on_success' => true,
        'max_retry_attempts' => 3,
        'retry_delay' => 60,
    ],

    // Security and validation
    'security' => [
        'require_confirmation' => true,
        'validate_integrity' => true,
        'check_permissions' => true,
        'allowed_extensions' => ['zip', 'tar.gz', 'tar'],
        'max_file_size' => 1073741824, // 1GB
    ],

    // Temporary directory
    'temp_directory' => storage_path('app/temp-restore'),

    // Cleanup temporary files
    'cleanup_temp_files' => true,
];
```

**Important**: This package reads backup configuration from `config/backup.php` (Spatie Laravel Backup configuration), so ensure your Spatie backup configuration is properly set up before using this package.

## Usage

### List Available Backups

```bash
php artisan backup:restore-complete --list
```

### Complete Restore (Database + Files)

```bash
# Restore latest backup
php artisan backup:restore-complete

# Restore from specific disk
php artisan backup:restore-complete --disk=google

# Restore specific backup file
php artisan backup:restore-complete --backup="app-backup-2024-01-15-10-30-00.zip"

# Force restore without prompts
php artisan backup:restore-complete --force
```

### Partial Restore

```bash
# Restore only database
php artisan backup:restore-complete --database-only

# Restore only files
php artisan backup:restore-complete --files-only
```

### Advanced Options

```bash
# Drop all tables before restoring database
php artisan backup:restore-complete --reset

# Restore to different database connection
php artisan backup:restore-complete --connection=mysql_backup
```

## Configuration Sections

### File Mappings

The `file_mappings` section defines how files from the backup should be mapped to local paths:

```php
'file_mappings' => [
    'public/uploads' => public_path('uploads'),
    'public/download' => public_path('download'),
    'storage/app' => storage_path('app'),
    'storage/plugins' => storage_path('plugins'),
],
```

This is essential for correctly mapping files from container paths (e.g., `/var/www/html/public/uploads`) to local paths (e.g., `public/uploads`).

### Database Configuration

The `database` section extends Laravel's existing database configuration from `config/database.php`:

```php
'database' => [
    'restore_options' => [
        'drop_tables_before_restore' => false,
        'skip_foreign_key_checks' => true,
        'use_single_transaction' => true,
    ],
],
```

**Note**: Database connections are automatically read from `config/database.php`, so you don't need to redefine them here.

### Health Checks

Add custom health checks to verify restoration success:

```php
'health_checks' => [
    // Built-in health checks
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
        'files' => [
            storage_path('app/public'),
            storage_path('logs'),
            storage_path('framework/cache'),
        ],
    ],
    
    // Custom health checks
    \App\HealthChecks\DatabaseConnectionCheck::class,
    \App\HealthChecks\FileIntegrityCheck::class,
],
```

### Security Settings

Configure security and validation settings:

```php
'security' => [
    'require_confirmation' => true,
    'validate_integrity' => true,
    'check_permissions' => true,
    'allowed_extensions' => ['zip', 'tar.gz', 'tar'],
    'max_file_size' => 1073741824, // 1GB
],
```

## Health Checks

Create custom health checks by extending the base HealthCheck class:

```php
<?php

namespace App\HealthChecks;

use Scryba\LaravelBackupCompleteRestore\HealthChecks\HealthCheck;

class DatabaseConnectionCheck extends HealthCheck
{
    public function run(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

## Error Handling

The package provides comprehensive error handling:

- **Logging**: All restoration activities are logged
- **Notifications**: Success/failure notifications via your backup notification system
- **Retry Logic**: Automatic retry for failed operations
- **Safety Backups**: Creates backup before restoration
- **Validation**: Backup file integrity verification

## Security Features

- **Confirmation Prompts**: Requires user confirmation for destructive operations
- **File Validation**: Validates backup file integrity and permissions
- **Size Limits**: Configurable maximum file size limits
- **Extension Validation**: Only allows specified file extensions

## Integration with Spatie Laravel Backup

This package is specifically designed to work with [Spatie Laravel Backup](https://spatie.be/docs/laravel-backup). It reads the backup archives created by Spatie Laravel Backup and restores both the database dumps and file archives.

### How It Works

1. **Backup Creation**: Use Spatie Laravel Backup to create backups
   ```bash
   php artisan backup:run
   ```

2. **Backup Storage**: Backups are stored according to your Spatie configuration in `config/backup.php`

3. **Restoration**: Use this package to restore from those backups
   ```bash
   php artisan backup:restore-complete
   ```

### Configuration Integration

This package automatically reads from your Spatie Laravel Backup configuration:

- **Backup Name**: From `config('backup.backup.name')`
- **Storage Disks**: From `config('backup.backup.destination.disks')`
- **File Paths**: From `config('backup.backup.source.files.include')`
- **Database Connections**: From `config('backup.backup.source.databases')`

## Troubleshooting

### Common Issues

1. **Permission Errors**: Ensure the web server has write permissions to the restoration directories
2. **Memory Limits**: Increase PHP memory limit for large backups
3. **Timeout Issues**: Adjust `max_execution_time` in the configuration
4. **Path Mapping**: Verify `file_mappings` and `container_base_path` settings
5. **Spatie Configuration**: Ensure Spatie Laravel Backup is properly configured

### Debug Mode

Enable debug mode to get more detailed information:

```bash
php artisan backup:restore-complete --verbose
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
