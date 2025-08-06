# Changelog

All notable changes to `laravel-backup-complete-restore` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2025-01-15

### Added
- **Consolidated Configuration**: Single `config/backup-complete-restore.php` file instead of multiple configuration files
- **Internal Health Check Classes**: Self-contained health check classes that extend dependency classes
- **Config System Integration**: Automatic merging of configuration into Laravel's config system
- **Spatie Integration Documentation**: Clear documentation of Spatie Laravel Backup dependency and integration
- **Leveraged Existing Laravel Config**: Uses existing `config/database.php`, `config/filesystems.php`, and `config/backup.php` instead of redefining settings
- **Compatibility Layer**: Automatic compatibility with `wnx/laravel-backup-restore` without creating physical files
- **Enhanced Documentation**: Comprehensive documentation with examples and best practices

### Changed
- **Configuration Structure**: Simplified configuration that extends existing Laravel configuration
- **Service Provider**: Updated to merge configuration automatically instead of creating physical files
- **Documentation**: Complete rewrite of README and documentation to emphasize Spatie integration
- **Package Description**: Updated to reflect consolidated configuration and internal classes
- **Keywords**: Added new keywords for health-checks and consolidated-config

### Improved
- **Maintainability**: Single source of truth for configuration
- **User Experience**: Cleaner installation and configuration process
- **Integration**: Better integration with existing Laravel configuration
- **Future-Proof**: Less vulnerable to breaking changes in dependencies

### Technical Improvements
- **No Configuration Duplication**: Database, files, and backup settings read from existing Laravel config
- **Self-Contained Package**: Internal classes reduce direct dependency on external packages
- **Config System Integration**: Uses Laravel's `mergeConfigFrom()` for seamless compatibility
- **Automatic Compatibility**: Creates compatibility layer for `wnx/laravel-backup-restore` automatically

## [1.3.0] - 2025-01-10

### Added
- Support for PHP 8.4
- Support for Laravel 12
- Updated author and funding information
- Comprehensive file restoration with exact path mapping
- Safety features including existing file backup
- Configurable file mappings and permissions
- Extensible health check system
- Multi-storage support (local, Google Drive, S3, etc.)
- Password protection for encrypted backups

### Changed
- Updated PHP version constraint to support 8.1, 8.2, 8.3, and 8.4
- Updated Laravel version constraint to support 10, 11, and 12
- Updated PHPUnit to support version 11
- Updated Orchestra Testbench to support version 10

### Fixed
- Proper handling of Spatie backup container paths (`var/www/html/...`)
- Correct file permission setting after restoration
- Comprehensive error handling and user feedback

## [1.2.0] - 2025-01-10

### Added
- Support for PHP 8.4
- Support for Laravel 12
- Updated author and funding information
- Comprehensive file restoration with exact path mapping
- Safety features including existing file backup
- Configurable file mappings and permissions
- Extensible health check system
- Multi-storage support (local, Google Drive, S3, etc.)
- Password protection for encrypted backups

### Changed
- Updated PHP version constraint to support 8.1, 8.2, 8.3, and 8.4
- Updated Laravel version constraint to support 10, 11, and 12
- Updated PHPUnit to support version 11
- Updated Orchestra Testbench to support version 10

### Fixed
- Proper handling of Spatie backup container paths (`var/www/html/...`)
- Correct file permission setting after restoration
- Comprehensive error handling and user feedback

## [1.0.0] - 2025-07-28

### Added
- Initial release
- Complete backup restoration for Spatie Laravel Backup
- Database and file restoration capabilities
- Command-line interface with multiple options
- Configuration system for file mappings
- Health check framework
- Safety features and confirmation prompts
- Support for multiple storage disks
- Automatic cleanup of temporary files

### Features
- `backup:restore-complete` command with options:
  - `--list` - List available backups
  - `--database-only` - Restore only database
  - `--files-only` - Restore only files
  - `--reset` - Drop all tables before database restore
  - `--force` - Skip confirmation prompts
  - `--disk` - Choose storage disk
  - `--backup` - Specify backup file
  - `--connection` - Choose database connection

### Requirements
- PHP 8.1+
- Laravel 10.0+
- Spatie Laravel Backup 8.0+
- WNX Laravel Backup Restore 1.6+
