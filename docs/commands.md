# Commands Guide

## Available Commands

This package provides several Artisan commands for managing backup restoration:

### Primary Command

#### `backup:restore-complete`

The main command for complete backup restoration. This command restores both database and files from Spatie Laravel Backup archives.

**Basic Usage:**
```bash
php artisan backup:restore-complete
```

**With Options:**
```bash
php artisan backup:restore-complete [options]
```

## Command Options

### Listing Backups

List available backups without performing restoration:

```bash
php artisan backup:restore-complete --list
```

**Output Example:**
```
📋 Available Backups

Disk: local
├── 2024-01-15-10-30-00.zip (15.2 MB)
├── 2024-01-15-09-15-00.zip (14.8 MB)
└── 2024-01-14-23-45-00.zip (14.9 MB)

Disk: s3
├── 2024-01-15-11-00-00.zip (15.1 MB)
└── 2024-01-15-08-30-00.zip (14.7 MB)
```

### Selective Restoration

#### Database Only
Restore only the database from the backup:

```bash
php artisan backup:restore-complete --database-only
```

#### Files Only
Restore only files from the backup:

```bash
php artisan backup:restore-complete --files-only
```

### Backup Selection

#### Specify Backup File
Choose a specific backup file to restore from:

```bash
php artisan backup:restore-complete --backup="2024-01-15-10-30-00.zip"
```

#### Specify Storage Disk
Choose which storage disk to read backups from:

```bash
php artisan backup:restore-complete --disk="s3"
```

### Database Options

#### Database Connection
Specify which database connection to use for restoration:

```bash
php artisan backup:restore-complete --connection="mysql"
```

#### Reset Database
Drop all tables before restoring the database:

```bash
php artisan backup:restore-complete --reset
```

**⚠️ Warning:** This will permanently delete all existing data in the database.

### Safety Options

#### Force Mode
Skip confirmation prompts (use with caution):

```bash
php artisan backup:restore-complete --force
```

#### Verbose Output
Show detailed progress information:

```bash
php artisan backup:restore-complete --verbose
```

## Complete Examples

### Basic Complete Restore
```bash
php artisan backup:restore-complete
```
This will:
1. List available backups
2. Prompt you to select a backup
3. Confirm the restoration
4. Restore both database and files
5. Run health checks

### Restore Specific Backup with Reset
```bash
php artisan backup:restore-complete --backup="2024-01-15-10-30-00.zip" --reset --force
```
This will:
1. Use the specified backup file
2. Drop all tables before database restore
3. Skip confirmation prompts
4. Restore both database and files

### Restore from S3 with Database Only
```bash
php artisan backup:restore-complete --disk="s3" --database-only --connection="mysql"
```
This will:
1. List backups from S3 disk
2. Restore only the database
3. Use the MySQL connection

### Restore Files Only with Verbose Output
```bash
php artisan backup:restore-complete --files-only --verbose
```
This will:
1. List available backups
2. Restore only files
3. Show detailed progress information

## Interactive Mode

When running the command without specific options, it will guide you through an interactive process:

```
📋 Available Backups

Disk: local
├── 2024-01-15-10-30-00.zip (15.2 MB)
├── 2024-01-15-09-15-00.zip (14.8 MB)
└── 2024-01-14-23-45-00.zip (14.9 MB)

Select a backup to restore [0-2]: 0

Selected backup: 2024-01-15-10-30-00.zip

What would you like to restore?
[0] Database and Files (Complete)
[1] Database Only
[2] Files Only

Select option [0-2]: 0

⚠️  This will overwrite existing data. Are you sure? (yes/no): yes

🔄 Starting complete restoration...
```

## Command Output

### Progress Indicators

The command provides real-time progress updates:

```
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

### Error Handling

If errors occur, the command provides detailed information:

```
❌ Restoration failed!

Error: Database connection failed
Details: Connection refused on localhost:3306

💡 Troubleshooting tips:
- Check your database configuration
- Ensure the database server is running
- Verify connection credentials
```

## Health Check Command

### `backup:health-check`

Run health checks on your restored backup:

```bash
php artisan backup:health-check
```

**Options:**
- `--connection`: Specify database connection
- `--verbose`: Show detailed health check information

**Output Example:**
```
🔍 Running health checks...

✅ Database has tables
✅ Database has records
✅ Critical files exist
✅ File integrity verified

🎉 All health checks passed!
```

## Custom Health Check Command

### `scryba:backup-health-check`

Run custom health checks specific to your application:

```bash
php artisan scryba:backup-health-check
```

This command uses your custom health check configuration from `config/backup-complete-restore.php`.

## Command Help

Get help for any command:

```bash
php artisan backup:restore-complete --help
```

This will show all available options and their descriptions.

## Next Steps

- [Configuration options](./configuration.md)
- [Health checks setup](./health-checks.md)
- [File mappings](./file-mappings.md)
- [Troubleshooting](./troubleshooting.md) 