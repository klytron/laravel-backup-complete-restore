# Troubleshooting Guide

## Common Issues and Solutions

This guide covers common issues you may encounter when using the Laravel Backup Complete Restore package and how to resolve them.

## Installation Issues

### Package Not Found

**Problem:** `composer require klytron/laravel-backup-complete-restore` fails.

**Solutions:**
1. Check if the package is available on Packagist:
   ```bash
   composer search klytron/laravel-backup-complete-restore
   ```

2. Ensure you have the correct repository configured:
   ```bash
   composer config repositories.klytron vcs https://github.com/klytron/laravel-backup-complete-restore
   ```

3. Try installing with the minimum stability:
   ```bash
   composer require klytron/laravel-backup-complete-restore --prefer-stable
   ```

### Command Not Found

**Problem:** `php artisan backup:restore-complete` command not found.

**Solutions:**
1. Clear Laravel's cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. Check if the service provider is registered in `config/app.php`:
   ```php
   'providers' => [
       // ...
       Klytron\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider::class,
   ],
   ```

3. Verify the package is properly installed:
   ```bash
   composer show klytron/laravel-backup-complete-restore
   ```

## Configuration Issues

### Configuration File Not Found

**Problem:** `config/backup-complete-restore.php` not found.

**Solutions:**
1. Publish the configuration:
   ```bash
   php artisan vendor:publish --provider="Klytron\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider"
   ```

2. Check if the file exists in the vendor directory:
   ```bash
   ls vendor/klytron/laravel-backup-complete-restore/config/
   ```

### Configuration Errors

**Problem:** PHP syntax errors in configuration file.

**Solutions:**
1. Validate PHP syntax:
   ```bash
   php -l config/backup-complete-restore.php
   ```

2. Check for missing commas or brackets in the configuration array.

3. Ensure all paths use Laravel helpers:
   ```php
   // Correct
   'backup_directory' => storage_path('app/backup-temp'),
   
   // Incorrect
   'backup_directory' => '/var/www/html/storage/app/backup-temp',
   ```

## Database Restoration Issues

### Database Connection Failed

**Problem:** `Database connection failed` error.

**Solutions:**
1. Check your database configuration in `config/database.php`:
   ```php
   'connections' => [
       'mysql' => [
           'driver' => 'mysql',
           'host' => env('DB_HOST', '127.0.0.1'),
           'port' => env('DB_PORT', '3306'),
           'database' => env('DB_DATABASE', 'forge'),
           'username' => env('DB_USERNAME', 'forge'),
           'password' => env('DB_PASSWORD', ''),
       ],
   ],
   ```

2. Verify database server is running:
   ```bash
   # For MySQL
   sudo systemctl status mysql
   
   # For PostgreSQL
   sudo systemctl status postgresql
   ```

3. Test database connection manually:
   ```bash
   php artisan tinker
   DB::connection()->getPdo();
   ```

### Permission Denied

**Problem:** `Permission denied` when accessing database.

**Solutions:**
1. Check database user permissions:
   ```sql
   GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Verify database user exists and has correct password.

3. Check if the database exists:
   ```sql
   SHOW DATABASES;
   CREATE DATABASE IF NOT EXISTS database_name;
   ```

### SQL Import Errors

**Problem:** SQL syntax errors during database restore.

**Solutions:**
1. Check the SQL dump file for syntax errors:
   ```bash
   head -50 backup-dump.sql
   ```

2. Verify the SQL file is not corrupted:
   ```bash
   file backup-dump.sql
   ```

3. Try importing manually to identify the issue:
   ```bash
   mysql -u username -p database_name < backup-dump.sql
   ```

## File Restoration Issues

### File Not Found

**Problem:** `File not found` errors during file restoration.

**Solutions:**
1. Check file mappings in configuration:
   ```php
   'file_mappings' => [
       'var/www/html/storage/app/public' => storage_path('app/public'),
       'var/www/html/public' => public_path(),
   ],
   ```

2. Verify the backup archive contains the expected files:
   ```bash
   unzip -l backup-archive.zip | grep "var/www/html"
   ```

3. Check container base path setting:
   ```php
   'container_base_path' => 'var/www/html',
   ```

### Permission Denied

**Problem:** `Permission denied` when writing files.

**Solutions:**
1. Check directory permissions:
   ```bash
   ls -la storage/app/public/
   chmod -R 755 storage/app/public/
   ```

2. Ensure web server user has write permissions:
   ```bash
   sudo chown -R www-data:www-data storage/
   sudo chmod -R 775 storage/
   ```

3. Configure file permissions in the package:
   ```php
   'files' => [
       'restore_options' => [
           'file_permissions' => 0644,
           'directory_permissions' => 0755,
       ],
   ],
   ```

### Disk Space Issues

**Problem:** `No space left on device` error.

**Solutions:**
1. Check available disk space:
   ```bash
   df -h
   ```

2. Clean up temporary files:
   ```bash
   rm -rf storage/app/backup-temp/*
   ```

3. Increase disk space or move to a larger partition.

## Backup Source Issues

### No Backups Found

**Problem:** `No backups found` message.

**Solutions:**
1. Check if backups exist on the specified disk:
   ```bash
   ls -la storage/app/laravel-backup/
   ```

2. Verify backup directory configuration:
   ```php
   'backup_sources' => [
       'restore_options' => [
           'backup_directory' => 'laravel-backup',
       ],
   ],
   ```

3. Check if Spatie Laravel Backup is creating backups:
   ```bash
   php artisan backup:run
   ```

### Wrong Disk Configuration

**Problem:** Backups not found on expected disk.

**Solutions:**
1. List backups from all disks:
   ```bash
   php artisan backup:restore-complete --list
   ```

2. Specify the correct disk:
   ```bash
   php artisan backup:restore-complete --disk="s3"
   ```

3. Check disk configuration in `config/filesystems.php`:
   ```php
   'disks' => [
       'local' => [
           'driver' => 'local',
           'root' => storage_path('app'),
       ],
       's3' => [
           'driver' => 's3',
           'key' => env('AWS_ACCESS_KEY_ID'),
           'secret' => env('AWS_SECRET_ACCESS_KEY'),
           'region' => env('AWS_DEFAULT_REGION'),
           'bucket' => env('AWS_BUCKET'),
       ],
   ],
   ```

## Health Check Issues

### Health Checks Failing

**Problem:** Health checks fail after restoration.

**Solutions:**
1. Check health check configuration:
   ```php
   'health_checks' => [
       \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
       \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
           'files' => [
               storage_path('app/public'),
               storage_path('logs'),
           ],
       ],
   ],
   ```

2. Run health checks manually to identify issues:
   ```bash
   php artisan backup:health-check --verbose
   ```

3. Disable problematic health checks temporarily:
   ```php
   'health_checks' => [
       // Comment out failing checks
       // \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords::class,
   ],
   ```

## Performance Issues

### Slow Restoration

**Problem:** Restoration process is very slow.

**Solutions:**
1. Increase PHP memory limit:
   ```ini
   memory_limit = 512M
   ```

2. Increase maximum execution time:
   ```php
   'database' => [
       'restore_options' => [
           'max_execution_time' => 600, // 10 minutes
       ],
   ],
   ```

3. Use database optimization options:
   ```php
   'database' => [
       'restore_options' => [
           'skip_foreign_key_checks' => true,
           'use_single_transaction' => true,
       ],
   ],
   ```

### Memory Exhaustion

**Problem:** `Allowed memory size exhausted` error.

**Solutions:**
1. Increase PHP memory limit in `php.ini`:
   ```ini
   memory_limit = 1G
   ```

2. Process large files in chunks:
   ```php
   'files' => [
       'restore_options' => [
           'chunk_size' => 1024 * 1024, // 1MB chunks
       ],
   ],
   ```

## Integration Issues

### Spatie Laravel Backup Not Found

**Problem:** `Class 'Spatie\Backup\BackupServiceProvider' not found`.

**Solutions:**
1. Install Spatie Laravel Backup:
   ```bash
   composer require spatie/laravel-backup
   ```

2. Publish Spatie configuration:
   ```bash
   php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
   ```

3. Verify Spatie is properly configured in `config/app.php`.

### WNX Laravel Backup Restore Issues

**Problem:** Compatibility issues with WNX package.

**Solutions:**
1. Check WNX package version:
   ```bash
   composer show wnx/laravel-backup-restore
   ```

2. Ensure compatibility configuration is loaded:
   ```php
   // This should be automatic, but verify in service provider
   $this->mergeConfigFrom(
       __DIR__.'/../config/backup-restore-compatibility.php',
       'backup-restore'
   );
   ```

## Debugging

### Enable Verbose Output

Use verbose mode to get detailed information:

```bash
php artisan backup:restore-complete --verbose
```

### Check Logs

Review Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

### Manual Testing

Test components individually:

1. **Test database connection:**
   ```bash
   php artisan tinker
   DB::connection()->getPdo();
   ```

2. **Test file operations:**
   ```bash
   php artisan tinker
   Storage::disk('local')->exists('laravel-backup');
   ```

3. **Test backup listing:**
   ```bash
   php artisan backup:restore-complete --list
   ```

## Getting Help

### Check Documentation

1. Review the [installation guide](./installation.md)
2. Check the [configuration guide](./configuration.md)
3. Review the [commands guide](./commands.md)

### Common Solutions

1. **Clear all caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   composer dump-autoload
   ```

2. **Reinstall the package:**
   ```bash
   composer remove klytron/laravel-backup-complete-restore
   composer require klytron/laravel-backup-complete-restore
   ```

3. **Check system requirements:**
   - PHP 8.1+
   - Laravel 10.0+
   - Spatie Laravel Backup 8.0+
   - WNX Laravel Backup Restore 1.6+

### Report Issues

If you continue to experience issues:

1. Check the [GitHub issues](https://github.com/klytron/laravel-backup-complete-restore/issues)
2. Create a new issue with:
   - Laravel version
   - PHP version
   - Package version
   - Error message
   - Steps to reproduce
   - Configuration details (sanitized)

## Next Steps

- [Installation guide](./installation.md)
- [Configuration options](./configuration.md)
- [Command usage](./commands.md)
- [Health checks setup](./health-checks.md)
- [File mappings](./file-mappings.md) 