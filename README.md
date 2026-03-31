# Laravel Backup Complete Restore

[![Latest Version on Packagist](https://img.shields.io/packagist/v/klytron/laravel-backup-complete-restore.svg)](https://packagist.org/packages/klytron/laravel-backup-complete-restore)
[![Total Downloads](https://img.shields.io/packagist/dt/klytron/laravel-backup-complete-restore.svg)](https://packagist.org/packages/klytron/laravel-backup-complete-restore)
[![License](https://img.shields.io/packagist/l/klytron/laravel-backup-complete-restore.svg)](https://packagist.org/packages/klytron/laravel-backup-complete-restore)
[![PHP Version](https://img.shields.io/packagist/php-v/klytron/laravel-backup-complete-restore.svg)](https://packagist.org/packages/klytron/laravel-backup-complete-restore)

A comprehensive Laravel package for complete backup restoration, including both database and file restoration from **Spatie Laravel Backup** archives with consolidated configuration and internal health checks.

## ✨ Features

- 🔄 **Complete Restoration**: Restore both database and files from Spatie Laravel Backup archives
- 🎯 **Consolidated Configuration**: Single configuration file that leverages existing Laravel config
- 🛡️ **Internal Health Checks**: Self-contained health check classes extending dependency packages
- 🔧 **Config System Integration**: Automatic compatibility with `wnx/laravel-backup-restore`
- 📁 **Smart File Mappings**: Intelligent mapping of container paths to local filesystem
- 🚀 **Multiple Storage Support**: Works with local, S3, Google Drive, and other storage disks
- 🔒 **Safety Features**: Backup existing files, confirmation prompts, and error handling
- 📊 **Progress Tracking**: Real-time progress indicators and detailed logging
- 🎨 **Beautiful CLI**: Colorful, emoji-rich command-line interface

## 🚀 Quick Start

### Installation

```bash
composer require klytron/laravel-backup-complete-restore
```

### Basic Usage

```bash
# List available backups
php artisan backup:restore-complete --list

# Restore complete backup (interactive)
php artisan backup:restore-complete

# Restore specific backup
php artisan backup:restore-complete --backup="2024-01-15-10-30-00.zip"

# Restore database only
php artisan backup:restore-complete --database-only

# Restore files only
php artisan backup:restore-complete --files-only
```

## 📋 Prerequisites

This package requires [Spatie Laravel Backup](https://github.com/spatie/laravel-backup) to be installed and configured in your application.

### Required Dependencies

- **PHP**: 8.1 or higher
- **Laravel**: 10.0, 11.0, 12.0, or 13.0
- **Spatie Laravel Backup**: 8.0 or higher
- **WNX Laravel Backup Restore**: 1.6 or higher

## 📚 Documentation

### Getting Started
- [📖 Installation Guide](docs/installation.md) - Complete setup and configuration
- [⚙️ Configuration Guide](docs/configuration.md) - Package configuration options
- [🔧 Commands Guide](docs/commands.md) - Available Artisan commands and options

### Advanced Usage
- [🛡️ Health Checks](docs/health-checks.md) - Built-in and custom health checks
- [📁 File Mappings](docs/file-mappings.md) - Understanding and configuring file mappings
- [🔍 Troubleshooting](docs/troubleshooting.md) - Common issues and solutions

## 🎯 Key Benefits

### Consolidated Configuration
Instead of multiple configuration files, this package uses a single `config/backup-complete-restore.php` file that leverages your existing Laravel configuration (`config/database.php`, `config/filesystems.php`, `config/backup.php`).

### Internal Health Checks
Self-contained health check classes that extend dependency packages, making the package more maintainable and reducing direct external dependencies.

### Seamless Integration
Automatic compatibility with `wnx/laravel-backup-restore` through config system integration - no physical files needed.

## 🔧 Configuration

The package automatically registers its configuration. To customize settings:

```bash
php artisan vendor:publish --provider="Klytron\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider"
```

### Basic Configuration

```php
// config/backup-complete-restore.php
return [
    'file_mappings' => [
        'var/www/html/storage/app/public' => storage_path('app/public'),
        'var/www/html/public' => public_path(),
        'var/www/html/app' => base_path('app'),
    ],
    
    'health_checks' => [
        \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
        \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
            'files' => [
                storage_path('app/public'),
                storage_path('logs'),
            ],
        ],
    ],
];
```

## 🛠️ Available Commands

| Command | Description |
|---------|-------------|
| `backup:restore-complete` | Complete backup restoration (database + files) |
| `backup:health-check` | Run health checks on restored backup |
| `klytron:backup-health-check` | Run custom health checks |

### Command Options

```bash
# List backups
php artisan backup:restore-complete --list

# Selective restoration
php artisan backup:restore-complete --database-only
php artisan backup:restore-complete --files-only

# Backup selection
php artisan backup:restore-complete --backup="filename.zip"
php artisan backup:restore-complete --disk="s3"

# Database options
php artisan backup:restore-complete --connection="mysql"
php artisan backup:restore-complete --reset

# Safety options
php artisan backup:restore-complete --force
php artisan backup:restore-complete --verbose
```

## 🔄 Integration with Spatie Laravel Backup

This package is designed to work seamlessly with Spatie Laravel Backup:

1. **Spatie creates backups** → This package restores them
2. **Same configuration** → Uses your existing Spatie backup configuration
3. **Same storage disks** → Works with all configured storage disks
4. **Same file structure** → Understands Spatie's backup archive format

## 🎨 Example Output

```
📋 Available Backups

Disk: local
├── 2024-01-15-10-30-00.zip (15.2 MB)
├── 2024-01-15-09-15-00.zip (14.8 MB)
└── 2024-01-14-23-45-00.zip (14.9 MB)

🔄 Starting complete restoration...

📦 Extracting backup archive...
   [████████████████████] 100%

🗄️  Restoring database...
   [████████████████████] 100%

📁 Restoring files...
   [████████████████████] 100%

✅ Restoration completed successfully!

🔍 Running health checks...
   ✅ Database has tables
   ✅ Critical files exist
   ✅ File integrity verified

🎉 Complete restoration finished successfully!
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Acknowledgments

- [Spatie](https://spatie.be/) for the excellent Laravel Backup package
- [WNX](https://github.com/wnx) for the Laravel Backup Restore package
- The Laravel community for inspiration and support

## 📞 Support

- 📧 **Email**: hi@klytron.com
- 🌐 **Website**: https://www.klytron.com
- ☕ **Support the project**: https://www.klytron.com/buy-me-a-coffee
- 🐛 **Issues**: [GitHub Issues](https://github.com/klytron/laravel-backup-complete-restore/issues)

---

**Made with ❤️ by [Michael K. Laweh](https://www.klytron.com)**
