# Installation Guide

## Prerequisites

This package is designed to work with [Spatie Laravel Backup](https://github.com/spatie/laravel-backup) and requires it to be installed and configured in your application.

### Required Dependencies

1. **Spatie Laravel Backup**: The foundation backup package that creates the archives this package restores from
   ```bash
   composer require spatie/laravel-backup
   ```

2. **Klytron Laravel Backup Complete Restore**: This package for restoration
   ```bash
   composer require klytron/laravel-backup-complete-restore
   ```

## Spatie Laravel Backup Configuration

Before using this package, ensure Spatie Laravel Backup is properly configured:

### 1. Publish Spatie Configuration

```bash
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

### 2. Configure Backup Settings

Edit `config/backup.php` to configure your backup sources:

```php
'backup' => [
    'name' => env('APP_NAME', 'laravel-backup'),
    'source' => [
        'files' => [
            'include' => [
                storage_path(),
                public_path(),
                base_path('app'),
                base_path('config'),
                base_path('database'),
                base_path('resources'),
                base_path('routes'),
            ],
            'exclude' => [
                storage_path('app/laravel-backup'),
                storage_path('app/backup-temp'),
                storage_path('logs'),
                storage_path('framework/cache'),
            ],
        ],
        'databases' => [
            'mysql', // or your database connection name
        ],
    ],
    'destination' => [
        'disks' => [
            'local',
            // 's3', // if using cloud storage
        ],
    ],
],
```

### 3. Configure Database Connections

Ensure your database connections are properly configured in `config/database.php`:

```php
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

### 4. Configure File Systems

Set up your file systems in `config/filesystems.php`:

```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
],
```

## Package Installation

### 1. Install via Composer

```bash
composer require klytron/laravel-backup-complete-restore
```

### 2. Publish Configuration (Optional)

The package will automatically register its configuration. If you want to customize settings:

```bash
php artisan vendor:publish --provider="Klytron\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider"
```

### 3. Verify Installation

Check that the command is available:

```bash
php artisan list | grep backup
```

You should see:
- `backup:restore-complete` - Complete restore of database AND files from Spatie Laravel Backup

## System Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.0, 11.0, or 12.0
- **Spatie Laravel Backup**: 8.0 or higher
- **WNX Laravel Backup Restore**: 1.6 or higher

## Next Steps

After installation, you can:

1. [Configure the package](./configuration.md)
2. [Learn about the command options](./commands.md)
3. [Set up health checks](./health-checks.md)
4. [Understand file mappings](./file-mappings.md) 