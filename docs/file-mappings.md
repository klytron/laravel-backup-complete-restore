# File Mappings Guide

## Overview

File mappings define how files from Spatie Laravel Backup archives are mapped to your local filesystem during restoration. This is essential because backup archives often contain files with container paths (like `var/www/html/`) that need to be mapped to your local Laravel application paths.

## How File Mappings Work

### Backup Archive Structure

Spatie Laravel Backup creates archives with files stored using container paths. For example:

```
backup-archive.zip
├── var/www/html/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   └── storage/
│       ├── app/
│       ├── logs/
│       └── framework/
```

### Mapping Process

File mappings translate these container paths to your local Laravel paths:

```php
'file_mappings' => [
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/public' => public_path(),
    'var/www/html/app' => base_path('app'),
    // ... more mappings
],
```

## Default File Mappings

The package includes sensible default mappings for common Laravel directories:

```php
'file_mappings' => [
    // Storage directories
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/storage/logs' => storage_path('logs'),
    'var/www/html/storage/framework/cache' => storage_path('framework/cache'),
    'var/www/html/storage/framework/sessions' => storage_path('framework/sessions'),
    'var/www/html/storage/framework/views' => storage_path('framework/views'),
    
    // Application directories
    'var/www/html/public' => public_path(),
    'var/www/html/app' => base_path('app'),
    'var/www/html/config' => base_path('config'),
    'var/www/html/database' => base_path('database'),
    'var/www/html/resources' => base_path('resources'),
    'var/www/html/routes' => base_path('routes'),
],
```

## Container Base Path

The `container_base_path` setting defines the base path used in backup archives:

```php
'container_base_path' => 'var/www/html',
```

This is typically the path inside Docker containers or server environments where your Laravel application runs.

## Customizing File Mappings

### Adding Custom Mappings

Add mappings for custom directories or files:

```php
'file_mappings' => [
    // Default mappings...
    
    // Custom application directories
    'var/www/html/custom' => base_path('custom'),
    'var/www/html/uploads' => public_path('uploads'),
    
    // Custom storage directories
    'var/www/html/storage/app/custom' => storage_path('app/custom'),
    
    // Specific files
    'var/www/html/.env.example' => base_path('.env.example'),
    'var/www/html/composer.json' => base_path('composer.json'),
],
```

### Environment-Specific Mappings

Use environment variables for flexible mappings:

```php
'container_base_path' => env('BACKUP_CONTAINER_BASE_PATH', 'var/www/html'),

'file_mappings' => [
    env('BACKUP_CONTAINER_BASE_PATH', 'var/www/html') . '/storage/app/public' => storage_path('app/public'),
    env('BACKUP_CONTAINER_BASE_PATH', 'var/www/html') . '/public' => public_path(),
    // ... more mappings
],
```

### Conditional Mappings

Create mappings based on your application structure:

```php
'file_mappings' => [
    // Always include these
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/public' => public_path(),
    
    // Include only if directories exist
    ...(is_dir(base_path('custom')) ? [
        'var/www/html/custom' => base_path('custom'),
    ] : []),
    
    ...(is_dir(public_path('uploads')) ? [
        'var/www/html/uploads' => public_path('uploads'),
    ] : []),
],
```

## File Mapping Examples

### Basic Laravel Application

```php
'file_mappings' => [
    // Core Laravel directories
    'var/www/html/app' => base_path('app'),
    'var/www/html/config' => base_path('config'),
    'var/www/html/database' => base_path('database'),
    'var/www/html/resources' => base_path('resources'),
    'var/www/html/routes' => base_path('routes'),
    
    // Public and storage
    'var/www/html/public' => public_path(),
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/storage/logs' => storage_path('logs'),
    'var/www/html/storage/framework/cache' => storage_path('framework/cache'),
],
```

### Application with Custom Directories

```php
'file_mappings' => [
    // Standard Laravel mappings
    'var/www/html/app' => base_path('app'),
    'var/www/html/config' => base_path('config'),
    'var/www/html/public' => public_path(),
    'var/www/html/storage/app/public' => storage_path('app/public'),
    
    // Custom application directories
    'var/www/html/modules' => base_path('modules'),
    'var/www/html/themes' => base_path('themes'),
    'var/www/html/uploads' => public_path('uploads'),
    'var/www/html/storage/app/uploads' => storage_path('app/uploads'),
    
    // Configuration files
    'var/www/html/.env.example' => base_path('.env.example'),
    'var/www/html/composer.json' => base_path('composer.json'),
],
```

### Multi-Environment Setup

```php
'file_mappings' => [
    // Base Laravel structure
    'var/www/html/app' => base_path('app'),
    'var/www/html/config' => base_path('config'),
    'var/www/html/public' => public_path(),
    'var/www/html/storage/app/public' => storage_path('app/public'),
    
    // Environment-specific directories
    'var/www/html/storage/app/' . env('APP_ENV', 'local') => storage_path('app/' . env('APP_ENV', 'local')),
    
    // Conditional mappings based on environment
    ...(env('APP_ENV') === 'production' ? [
        'var/www/html/storage/app/production' => storage_path('app/production'),
    ] : []),
],
```

## File Mapping Best Practices

### 1. Map Only Necessary Directories

Only map directories that you actually need to restore:

```php
// Good: Only essential directories
'file_mappings' => [
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/public' => public_path(),
    'var/www/html/app' => base_path('app'),
],

// Avoid: Mapping everything
'file_mappings' => [
    'var/www/html' => base_path(), // This could overwrite important files
],
```

### 2. Use Absolute Paths

Always use Laravel's path helpers for consistency:

```php
// Good: Using Laravel path helpers
'file_mappings' => [
    'var/www/html/storage/app/public' => storage_path('app/public'),
    'var/www/html/public' => public_path(),
    'var/www/html/app' => base_path('app'),
],

// Avoid: Hard-coded paths
'file_mappings' => [
    'var/www/html/storage/app/public' => '/var/www/html/storage/app/public',
],
```

### 3. Consider File Permissions

Be aware of file permissions when mapping:

```php
'files' => [
    'restore_options' => [
        'file_permissions' => 0644,
        'directory_permissions' => 0755,
    ],
],
```

### 4. Handle Existing Files

Configure how to handle existing files:

```php
'files' => [
    'restore_options' => [
        'backup_existing_files' => true,
        'backup_directory' => storage_path('app/backup-temp'),
    ],
],
```

## Troubleshooting File Mappings

### Common Issues

#### 1. Files Not Restored

**Problem:** Files are not being restored to expected locations.

**Solution:** Check your file mappings:
```php
// Verify the mapping exists
'var/www/html/storage/app/public' => storage_path('app/public'),
```

#### 2. Wrong Container Base Path

**Problem:** Files are not found in the expected container path.

**Solution:** Update the container base path:
```php
'container_base_path' => 'app', // instead of 'var/www/html'
```

#### 3. Permission Errors

**Problem:** Permission denied errors during restoration.

**Solution:** Check directory permissions and configure file permissions:
```php
'files' => [
    'restore_options' => [
        'file_permissions' => 0644,
        'directory_permissions' => 0755,
    ],
],
```

### Debugging File Mappings

Enable verbose output to see file mapping details:

```bash
php artisan backup:restore-complete --verbose
```

This will show:
- Which files are being mapped
- Where they're being restored
- Any mapping issues

## Advanced File Mapping

### Dynamic Mappings

Create mappings based on your application structure:

```php
'file_mappings' => function() {
    $mappings = [
        'var/www/html/storage/app/public' => storage_path('app/public'),
        'var/www/html/public' => public_path(),
    ];
    
    // Add custom mappings based on application structure
    if (is_dir(base_path('modules'))) {
        $mappings['var/www/html/modules'] = base_path('modules');
    }
    
    if (is_dir(public_path('uploads'))) {
        $mappings['var/www/html/uploads'] = public_path('uploads');
    }
    
    return $mappings;
},
```

### Selective File Restoration

Map only specific file types:

```php
'file_mappings' => [
    // Only restore specific file types
    'var/www/html/storage/app/public/images' => storage_path('app/public/images'),
    'var/www/html/storage/app/public/documents' => storage_path('app/public/documents'),
    
    // Exclude certain directories
    // 'var/www/html/storage/logs' => storage_path('logs'), // Commented out
],
```

## Next Steps

- [Configuration options](./configuration.md)
- [Command usage](./commands.md)
- [Health checks setup](./health-checks.md)
- [Troubleshooting](./troubleshooting.md) 