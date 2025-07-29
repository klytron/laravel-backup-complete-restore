# Laravel Backup Complete Restore

A Laravel package that provides complete backup restoration for Spatie Laravel Backup - restores both database AND files to their exact locations.

## Features

- ✅ **Complete Restoration**: Restores both database and files from Spatie Laravel Backup
- ✅ **Exact File Placement**: Files are restored to their original locations
- ✅ **Safety Features**: Backs up existing files before restoration
- ✅ **Configurable Mappings**: Customize how backup paths map to local paths
- ✅ **Permission Management**: Automatically sets correct file permissions
- ✅ **Health Checks**: Extensible health check system
- ✅ **Multiple Storage Support**: Works with local, Google Drive, S3, etc.
- ✅ **Password Protection**: Supports encrypted backups

## Requirements

- PHP 8.1+ (supports PHP 8.1, 8.2, 8.3, 8.4)
- Laravel 10.0+ (supports Laravel 10, 11, 12)
- Spatie Laravel Backup 8.0+
- WNX Laravel Backup Restore 1.6+

## Installation

Install the package via Composer:

```bash
composer require scryba/laravel-backup-complete-restore
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="backup-complete-restore-config"
```

Optionally, publish the health checks:

```bash
php artisan vendor:publish --tag="backup-complete-restore-health-checks"
```

## Configuration

The package configuration is published to `config/backup-complete-restore.php`:

```php
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

    // Custom health checks
    'health_checks' => [
        // Add your health check classes here
    ],
];
```

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

## How It Works

1. **Database Restoration**: Uses the existing `backup:restore` command from WNX Laravel Backup Restore
2. **File Extraction**: Extracts the backup ZIP to a temporary directory
3. **Path Mapping**: Maps backup paths (e.g., `var/www/html/public/uploads`) to local paths
4. **File Restoration**: Copies files to their correct locations with proper permissions
5. **Health Checks**: Runs optional health checks to verify restoration
6. **Cleanup**: Removes temporary files

## File Mapping

The package automatically handles the path differences between backup containers and your local environment:

| Backup Path | Local Path | Description |
|-------------|------------|-------------|
| `var/www/html/public/uploads` | `public/uploads` | User uploads |
| `var/www/html/public/download` | `public/download` | Download files |
| `var/www/html/storage/app` | `storage/app` | Application storage |
| `var/www/html/storage/plugins` | `storage/plugins` | Plugin storage |

## Safety Features

- **Existing File Backup**: Creates timestamped backups of existing directories
- **Permission Management**: Sets appropriate permissions for web and storage directories
- **Confirmation Prompts**: Requires confirmation before destructive operations
- **Error Handling**: Comprehensive error handling with detailed messages

## Health Checks

Create custom health checks by extending the base health check class:

```php
<?php

namespace App\HealthChecks;

class CustomHealthCheck
{
    public function run()
    {
        // Your health check logic
        return true; // or false
    }
}
```

Add to configuration:

```php
'health_checks' => [
    \App\HealthChecks\CustomHealthCheck::class,
],
```

## Example Output

```
🔄 Complete Backup Restore Tool

⚠️  WARNING: This will restore your database AND files from backup!
⚠️  This operation will overwrite your current data and files.

Are you sure you want to continue? (yes/no) [no]: yes

📁 Using backup: app-backup-2024-01-15-10-30-00.zip

🗄️  Restoring database...
🔐 Using configured backup password
🚀 Starting database restore...
✅ Database restored successfully

📁 Restoring files...
✅ Backup extracted successfully
📁 Restoring public/uploads...
📦 Backing up existing /var/www/html/public/uploads to /var/www/html/public/uploads_backup_1705312200
✅ Restored public/uploads
📁 Restoring storage/app...
✅ Restored storage/app
🔧 Fixing file permissions...
✅ File permissions updated
📊 Files restored: 2, Failed: 0
🧹 Cleaned up temporary files

✅ Complete restore finished successfully!
🎉 Your application is ready to use!

💡 Recommended next steps:
   • Clear application cache: php artisan cache:clear
   • Clear config cache: php artisan config:clear
   • Check file permissions
   • Test critical functionality
```

## Version Compatibility

| Package Version | PHP Version | Laravel Version | Status |
|----------------|-------------|-----------------|---------|
| 1.x | 8.1, 8.2, 8.3, 8.4 | 10, 11, 12 | ✅ Active |

## Testing

Run the tests with:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email contact@michael.laweitech.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- Built on top of [Spatie Laravel Backup](https://github.com/spatie/laravel-backup)
- Uses [WNX Laravel Backup Restore](https://github.com/stefanzweifel/laravel-backup-restore) for database restoration
- Created by [Michael K. Laweh](https://michael.laweitech.com)
