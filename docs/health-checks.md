# Health Checks Guide

## Overview

Health checks are automated tests that run after a backup restoration to verify that the restoration was successful. This package includes built-in health checks and allows you to create custom ones.

## Built-in Health Checks

### Database Health Checks

#### DatabaseHasTables

Verifies that the restored database contains tables.

**Configuration:**
```php
'health_checks' => [
    \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
],
```

**What it checks:**
- Database connection is working
- At least one table exists in the database
- Database structure is intact

#### DatabaseHasRecords

Verifies that the restored database contains records in tables.

**Configuration:**
```php
'health_checks' => [
    \Klytron\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords::class,
],
```

**What it checks:**
- Tables contain data
- Database is not empty
- Data restoration was successful

**Note:** This check is optional and can be commented out if you have empty tables.

### File Health Checks

#### FilesExist

Verifies that critical files exist after restoration.

**Configuration:**
```php
'health_checks' => [
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
        'files' => [
            storage_path('app/public'),
            storage_path('logs'),
            storage_path('framework/cache'),
            public_path('index.php'),
            base_path('.env'),
        ],
    ],
],
```

**What it checks:**
- Critical directories exist
- Essential files are present
- File structure is intact

## Custom Health Checks

### Creating Custom Health Checks

You can create custom health checks by extending the base health check class:

```php
<?php

namespace App\HealthChecks;

use Scryba\LaravelBackupCompleteRestore\HealthChecks\HealthCheck;

class CustomDatabaseCheck extends HealthCheck
{
    public function name(): string
    {
        return 'Custom Database Check';
    }

    public function description(): string
    {
        return 'Verifies custom database requirements';
    }

    public function run(): bool
    {
        // Your custom logic here
        $userCount = \DB::table('users')->count();
        
        if ($userCount === 0) {
            $this->addError('No users found in database');
            return false;
        }

        return true;
    }
}
```

### Registering Custom Health Checks

Add your custom health checks to the configuration:

```php
'health_checks' => [
    // Built-in checks
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
        'files' => [
            storage_path('app/public'),
            storage_path('logs'),
        ],
    ],
    
    // Custom checks
    \App\HealthChecks\CustomDatabaseCheck::class,
    \App\HealthChecks\ApplicationSpecificCheck::class => [
        'config' => 'custom_value',
    ],
],
```

### Health Check Base Class

All health checks should extend the base `HealthCheck` class:

```php
<?php

namespace Scryba\LaravelBackupCompleteRestore\HealthChecks;

abstract class HealthCheck
{
    protected array $errors = [];
    protected array $warnings = [];
    protected array $info = [];

    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function run(): bool;

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    protected function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    protected function addInfo(string $message): void
    {
        $this->info[] = $message;
    }
}
```

## Health Check Examples

### Application-Specific Health Check

```php
<?php

namespace App\HealthChecks;

use Scryba\LaravelBackupCompleteRestore\HealthChecks\HealthCheck;

class ApplicationHealthCheck extends HealthCheck
{
    public function name(): string
    {
        return 'Application Health Check';
    }

    public function description(): string
    {
        return 'Verifies application-specific requirements';
    }

    public function run(): bool
    {
        // Check if required tables exist
        $requiredTables = ['users', 'posts', 'categories'];
        
        foreach ($requiredTables as $table) {
            if (!\Schema::hasTable($table)) {
                $this->addError("Required table '{$table}' is missing");
                return false;
            }
        }

        // Check if admin user exists
        $adminUser = \DB::table('users')->where('role', 'admin')->first();
        if (!$adminUser) {
            $this->addWarning('No admin user found');
        }

        // Check file permissions
        $storagePath = storage_path('app/public');
        if (!is_writable($storagePath)) {
            $this->addError("Storage directory is not writable: {$storagePath}");
            return false;
        }

        return true;
    }
}
```

### Configuration-Based Health Check

```php
<?php

namespace App\HealthChecks;

use Scryba\LaravelBackupCompleteRestore\HealthChecks\HealthCheck;

class ConfigurableHealthCheck extends HealthCheck
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function name(): string
    {
        return 'Configurable Health Check';
    }

    public function description(): string
    {
        return 'Verifies configurable requirements';
    }

    public function run(): bool
    {
        $requiredFiles = $this->config['required_files'] ?? [];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                $this->addError("Required file not found: {$file}");
                return false;
            }
        }

        $minRecordCount = $this->config['min_record_count'] ?? 0;
        if ($minRecordCount > 0) {
            $recordCount = \DB::table('users')->count();
            if ($recordCount < $minRecordCount) {
                $this->addWarning("Only {$recordCount} users found, expected at least {$minRecordCount}");
            }
        }

        return true;
    }
}
```

## Running Health Checks

### Automatic Health Checks

Health checks run automatically after restoration when enabled:

```php
'restoration' => [
    'run_health_checks' => true,
],
```

### Manual Health Checks

Run health checks manually using the command:

```bash
php artisan backup:health-check
```

**Options:**
- `--connection`: Specify database connection
- `--verbose`: Show detailed information

### Custom Health Check Command

Run your custom health checks:

```bash
php artisan klytron:backup-health-check
```

## Health Check Output

### Success Output

```
🔍 Running health checks...

✅ Database has tables
✅ Database has records
✅ Critical files exist
✅ Application health check passed

🎉 All health checks passed!
```

### Warning Output

```
🔍 Running health checks...

✅ Database has tables
✅ Database has records
⚠️  No admin user found
✅ Critical files exist

🎉 Health checks completed with warnings!
```

### Error Output

```
🔍 Running health checks...

✅ Database has tables
❌ Required table 'posts' is missing
❌ Storage directory is not writable: /path/to/storage

❌ Health checks failed!

Errors:
- Required table 'posts' is missing
- Storage directory is not writable: /path/to/storage
```

## Best Practices

### 1. Keep Health Checks Focused

Each health check should verify one specific aspect:

```php
// Good: Focused on one thing
class DatabaseConnectionCheck extends HealthCheck
{
    public function run(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            $this->addError('Database connection failed: ' . $e->getMessage());
            return false;
        }
    }
}
```

### 2. Provide Clear Error Messages

Make error messages actionable:

```php
protected function addError(string $message): void
{
    $this->errors[] = $message . ' - Please check your configuration and try again.';
}
```

### 3. Use Warnings for Non-Critical Issues

Use warnings for issues that don't prevent restoration but should be noted:

```php
if ($userCount === 0) {
    $this->addWarning('No users found - you may need to create an admin user');
}
```

### 4. Test Your Health Checks

Always test your custom health checks:

```php
// Test your health check
$check = new CustomHealthCheck();
$result = $check->run();
$errors = $check->getErrors();
```

## Configuration Examples

### Basic Configuration

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

### Advanced Configuration

```php
'health_checks' => [
    // Database checks
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasTables::class,
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\DatabaseHasRecords::class,
    
    // File checks
    \Scryba\LaravelBackupCompleteRestore\HealthChecks\Checks\FilesExist::class => [
        'files' => [
            storage_path('app/public'),
            storage_path('logs'),
            storage_path('framework/cache'),
            public_path('index.php'),
            base_path('.env'),
        ],
    ],
    
    // Custom checks
    \App\HealthChecks\ApplicationHealthCheck::class,
    \App\HealthChecks\ConfigurableHealthCheck::class => [
        'required_files' => [
            storage_path('app/public/uploads'),
            public_path('assets/css'),
        ],
        'min_record_count' => 5,
    ],
],
```

## Next Steps

- [Configuration options](./configuration.md)
- [Command usage](./commands.md)
- [File mappings](./file-mappings.md)
- [Troubleshooting](./troubleshooting.md) 