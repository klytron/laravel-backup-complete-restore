# Changelog

All notable changes to `laravel-backup-complete-restore` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **`--dry-run` restore previews**: `backup:restore-complete --dry-run` downloads the archive, reports its date, entry count, compressed/uncompressed size, detected database dumps, and the database/files restore plan (targets included) — restoring nothing.
- **Streamed extraction progress**: archive entries are now extracted one at a time behind a progress bar with a files/bytes summary (`📊 Extracted N/M files (...)`), so multi-GB restores no longer look hung.
- **`--keep-temp` flag**: skips temp-dir cleanup on success and failure, printing the kept path for debugging (pairs with `cleanup_temp_files` config).
- **Restoration temp janitor**: new `klytron:backup:cleanup:restoration` command purges stale `temp-restore-*` / `temp-check-*` directories, with `--older-than=` (minutes, default 60), `--dry-run`, and `--force`.
- **SQLite post-restore verification**: `PRAGMA integrity_check` runs automatically after sqlite restores (config `restoration.verify_sqlite`, skip with `--skip-verification`), plus an opt-in boot probe (fresh reconnect + trivial query, optional `restoration.boot_probe_command`; config `restoration.sqlite_boot_probe` or `--boot-probe`) and a `🔍 SQLite verification: ...` summary line in restore output.
- **Multi-Driver Database Support**: Driver-aware `dropAllTables()` using `Schema::connection()->dropAllTables()` with robust fallbacks for SQLite (`PRAGMA foreign_keys = OFF`), PostgreSQL (`CASCADE`), and MySQL/MariaDB.
- **Multi-Driver Health Checks**: `checkDatabaseTables()` now natively supports SQLite, PostgreSQL, SQL Server, and MySQL via `Schema::getTables()` and driver catalog queries.
- **Resource Limit Controls**: Configured `restoration.max_execution_time` and `restoration.memory_limit` are now applied during restoration.
- **Post-Restore Actions**: Automatically executes post-restore health checks and optional cache clearing (`cache:clear`, `config:clear`) when configured.
- **Repository Hygiene**: Added `.gitattributes` to exclude tests and development assets from distribution packages, and added standard `.gitignore`.

### Changed
- **Single-Pass Archive Extraction**: Refactored `handle()` and `extractBackup()` to download and extract the backup archive once via memory-efficient PSR-7/stream copying, eliminating redundant downloads for database and file restoration.
- **Guaranteed Temp Directory Cleanup**: Wrapped temporary extraction directories in `try ... finally` blocks to prevent orphaned `temp-restore-*` and `temp-check-*` directories on aborts or exceptions.
- **Widen Dependencies**: Widened `spatie/laravel-backup` constraint to `^8.0|^9.0|^10.0` for full Laravel 13 compatibility.
- **Pruned Dead Configuration**: Removed unsupported imaginary settings from `config/backup-complete-restore.php` and `config/backup-restore-compatibility.php`, ensuring every config option is functional and documented.

### Fixed
- **App Configuration Health Check**: Corrected config key lookups from `APP_NAME` env keys to standard Laravel `app.name`, `app.env`, `app.key`, `app.debug` configuration paths with env fallbacks.
- **SQLite Connection Info Crash**: Null-coalesced host and port when logging database connection info to prevent undefined array key warnings on SQLite.

## [1.6.1] - 2026-03-31

### Fixed
- Improved file mapping resolution for nested storage paths.

## [1.6.0] - 2026-03-31

### Added
- Support for Laravel 13.
- Improved database dump parsing for large SQL files with streaming support.

## [1.5.0] - 2025-08-01

### Added
- Health check commands with consolidated reporting.
- Enhanced password decryption support for zip archives.

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
