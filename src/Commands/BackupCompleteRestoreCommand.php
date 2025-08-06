<?php

namespace Scryba\LaravelBackupCompleteRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use ZipArchive;
use Exception;

class BackupCompleteRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore-complete 
                            {--disk=local : The disk to restore from (local, google)}
                            {--backup= : Specific backup file to restore (optional)}
                            {--connection=mysql : Database connection to restore to}
                            {--reset : Drop all tables before restoring}
                            {--database-only : Restore only database}
                            {--files-only : Restore only files}
                            {--list : List available backups}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete restore of database AND files from Spatie Laravel Backup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('list')) {
            return $this->listBackups();
        }

        $this->info('🔄 Complete Backup Restore Tool');
        $this->line('');

        // Safety warnings
        if (!$this->option('force')) {
            $this->warn('⚠️  WARNING: This will restore your database AND files from backup!');
            $this->warn('⚠️  This operation will overwrite your current data and files.');
            $this->line('');
            
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('❌ Restore operation cancelled.');
                return 1;
            }
        }

        $disk = $this->option('disk');
        $backup = $this->option('backup');
        $databaseOnly = $this->option('database-only');
        $filesOnly = $this->option('files-only');

        try {
            // Find the backup file
            $backupFile = $this->findBackupFile($disk, $backup);
            if (!$backupFile) {
                $this->error('❌ Backup file not found!');
                return 1;
            }

            $this->info("📁 Using backup: " . basename($backupFile));
            $this->line('');

            $success = true;

            // Check if this backup contains database dumps
            $hasDatabase = $this->backupContainsDatabase($disk, $backupFile);
            
            // Restore database first (if backup contains database and not files-only)
            if (!$filesOnly && $hasDatabase) {
                $this->info('🗄️  Restoring database...');
                if (!$this->restoreDatabase($disk, $backupFile)) {
                    $success = false;
                }
            } elseif (!$filesOnly && !$hasDatabase) {
                $this->info('ℹ️  No database found in backup (files-only backup)');
            }

            // Restore files (if not database-only)
            if (!$databaseOnly && $success) {
                $this->info('📁 Restoring files...');
                
                // Extract backup to temporary location
                $tempDir = $this->extractBackup($disk, $backupFile);
                if (!$tempDir) {
                    $this->error('❌ Failed to extract backup!');
                    return 1;
                }

                if (!$this->restoreFiles($tempDir)) {
                    $success = false;
                }

                // Cleanup
                if (config('backup-complete-restore.cleanup_temp_files', true)) {
                    $this->cleanup($tempDir);
                }
            }

            if ($success) {
                $this->info('');
                $this->info('✅ Complete restore finished successfully!');
                $this->info('🎉 Your application is ready to use!');
                
                // Suggest next steps
                $this->line('');
                $this->info('💡 Recommended next steps:');
                $this->line('   • Clear application cache: php artisan cache:clear');
                $this->line('   • Clear config cache: php artisan config:clear');
                $this->line('   • Check file permissions');
                $this->line('   • Test critical functionality');
                
                return 0;
            } else {
                $this->error('❌ Restore failed!');
                return 1;
            }

        } catch (Exception $e) {
            $this->error('❌ Restore failed with error: ' . $e->getMessage());
            return 1;
        }
    }

    private function findBackupFile($disk, $backup = null)
    {
        $backupName = config('backup.backup.name');
        $backupPath = $backupName;

        if ($backup) {
            // Specific backup file - check if it's already a full path or just filename
            if (str_contains($backup, '/')) {
                // Full path provided
                $path = $backup;
            } else {
                // Just filename, construct path in backup directory
                $path = "{$backupPath}/{$backup}";
            }
            
            if (Storage::disk($disk)->exists($path)) {
                return $path;
            }
            
            // If not found, try looking for the file directly in the backup directory
            // This handles cases where the backup name might be different
            $files = Storage::disk($disk)->files($backupPath);
            foreach ($files as $file) {
                if (basename($file) === $backup) {
                    return $file;
                }
            }
            
            return null;
        }

        // Find latest backup
        if (!Storage::disk($disk)->exists($backupPath)) {
            return null;
        }

        $files = Storage::disk($disk)->files($backupPath);
        $backups = array_filter($files, fn($file) => str_ends_with($file, '.zip'));

        if (empty($backups)) {
            return null;
        }

        // Sort by date (newest first)
        usort($backups, fn($a, $b) => Storage::disk($disk)->lastModified($b) - Storage::disk($disk)->lastModified($a));

        return $backups[0];
    }

    private function extractBackup($disk, $backupFile)
    {
        $tempBase = config('backup-complete-restore.temp_directory', storage_path('app/temp-restore'));
        $tempDir = $tempBase . '-' . time();
        File::makeDirectory($tempDir, 0755, true);

        // Download backup file to temp location
        $localBackupPath = $tempDir . '/backup.zip';
        $backupContent = Storage::disk($disk)->get($backupFile);
        File::put($localBackupPath, $backupContent);

        // Extract ZIP file
        $zip = new ZipArchive;
        if ($zip->open($localBackupPath) === TRUE) {
            $zip->extractTo($tempDir);
            $zip->close();
            
            // Remove the zip file
            File::delete($localBackupPath);
            
            $this->info('✅ Backup extracted successfully');
            return $tempDir;
        } else {
            $this->error('❌ Failed to extract backup ZIP file');
            File::deleteDirectory($tempDir);
            return null;
        }
    }

    private function restoreDatabase($disk, $backupFile)
    {
        try {
            $this->info('📥 Downloading backup file...');
            
            // Download the backup file to a temporary location
            $tempDir = storage_path('app/temp-restore-' . time());
            File::makeDirectory($tempDir, 0755, true);
            
            $localBackupPath = $tempDir . '/backup.zip';
            $this->info('⏳ Downloading from ' . $disk . ' disk...');
            
            // Download with progress indicator
            $backupContent = Storage::disk($disk)->get($backupFile);
            if (!$backupContent) {
                $this->error('❌ Failed to download backup file from ' . $disk . ' disk');
                File::deleteDirectory($tempDir);
                return false;
            }
            
            File::put($localBackupPath, $backupContent);
            $this->info('✅ Backup file downloaded successfully (' . $this->formatBytes(strlen($backupContent)) . ')');
            
            // Extract the backup
            $this->info('📦 Extracting backup archive...');
            $extractDir = $tempDir . '/extracted';
            File::makeDirectory($extractDir, 0755, true);
            
            $zip = new ZipArchive;
            if ($zip->open($localBackupPath) !== TRUE) {
                $this->error('❌ Failed to open backup ZIP file');
                File::deleteDirectory($tempDir);
                return false;
            }
            
            // Check if backup requires password
            $password = env('BACKUP_ARCHIVE_PASSWORD');
            if ($password) {
                $zip->setPassword($password);
                $this->info('🔐 Using configured backup password');
            }
            
            $zip->extractTo($extractDir);
            $zip->close();
            $this->info('✅ Backup extracted successfully');
            
            // Find the database dump file
            $dbFiles = File::glob($extractDir . '/**/*.sql');
            if (empty($dbFiles)) {
                $this->error('❌ No SQL dump file found in backup');
                File::deleteDirectory($tempDir);
                return false;
            }
            
            $dbFile = $dbFiles[0];
            $this->info('🗄️  Found database dump: ' . basename($dbFile));
            
            // Reset database if requested
            if ($this->option('reset')) {
                $this->warn('🗑️  Dropping all existing tables...');
                $this->dropAllTables();
            }
            
            // Restore the database
            $this->info('🚀 Restoring database from dump...');
            $connection = $this->option('connection') ?: config('database.default');
            
            if ($this->importDatabaseDump($dbFile, $connection)) {
                $this->info('✅ Database restored successfully');
                File::deleteDirectory($tempDir);
                return true;
            } else {
                $this->error('❌ Database restore failed');
                File::deleteDirectory($tempDir);
                return false;
            }
            
        } catch (Exception $e) {
            $this->error('❌ Database restore error: ' . $e->getMessage());
            if (isset($tempDir) && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            return false;
        }
    }

    private function restoreFiles($tempDir)
    {
        $restored = 0;
        $failed = 0;

        // Get configuration
        $containerBasePath = config('backup-complete-restore.container_base_path', 'var/www/html');
        $fileMappings = config('backup-complete-restore.file_mappings', []);
        
        $fullContainerPath = $tempDir . '/' . $containerBasePath;

        if (!File::exists($fullContainerPath)) {
            $this->error('❌ Backup does not contain expected file structure');
            return false;
        }

        foreach ($fileMappings as $backupPath => $localPath) {
            $fullBackupPath = $fullContainerPath . '/' . $backupPath;
            
            if (File::exists($fullBackupPath)) {
                $this->info("📁 Restoring {$backupPath}...");
                
                if ($this->restoreDirectory($fullBackupPath, $localPath)) {
                    $this->info("✅ Restored {$backupPath}");
                    $restored++;
                } else {
                    $this->error("❌ Failed to restore {$backupPath}");
                    $failed++;
                }
            } else {
                $this->warn("⚠️  {$backupPath} not found in backup (skipping)");
            }
        }

        // Fix permissions after restoration
        if ($restored > 0) {
            $this->info('🔧 Fixing file permissions...');
            $this->fixPermissions();
        }

        // Run custom health checks
        $this->runHealthChecks();

        $this->info("📊 Files restored: {$restored}, Failed: {$failed}");
        return $failed === 0;
    }

    private function restoreDirectory($source, $destination)
    {
        try {
            // Backup existing directory if configured
            if (config('backup-complete-restore.backup_existing_files', true) && File::exists($destination)) {
                $backupPath = $destination . '_backup_' . time();
                $this->info("📦 Backing up existing {$destination} to {$backupPath}");
                File::copyDirectory($destination, $backupPath);
            }

            // Create destination directory if it doesn't exist
            if (!File::exists($destination)) {
                File::makeDirectory($destination, 0755, true);
            }

            // Copy files recursively, merging with existing content
            $this->copyDirectoryContents($source, $destination);

            return true;
        } catch (Exception $e) {
            $this->error("Error restoring {$destination}: " . $e->getMessage());
            return false;
        }
    }

    private function copyDirectoryContents($source, $destination)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!File::exists($target)) {
                    File::makeDirectory($target, 0755, true);
                }
            } else {
                // Create parent directory if it doesn't exist
                $targetDir = dirname($target);
                if (!File::exists($targetDir)) {
                    File::makeDirectory($targetDir, 0755, true);
                }

                // Copy the file
                File::copy($item->getPathname(), $target);
            }
        }
    }

    private function fixPermissions()
    {
        try {
            $dirPerms = config('backup-complete-restore.permissions.directories', 0755);
            $filePerms = config('backup-complete-restore.permissions.files', 0644);

            // Fix permissions for web-accessible directories
            $webDirs = config('backup-complete-restore.web_directories', []);
            foreach ($webDirs as $relativeDir) {
                $dir = base_path($relativeDir);
                if (File::exists($dir)) {
                    chmod($dir, $dirPerms);
                    $this->setDirectoryPermissions($dir, $dirPerms, $filePerms);
                }
            }

            // Fix permissions for storage directories
            $storageDirs = config('backup-complete-restore.storage_directories', []);
            foreach ($storageDirs as $relativeDir) {
                $dir = base_path($relativeDir);
                if (File::exists($dir)) {
                    chmod($dir, $dirPerms);
                    $this->setDirectoryPermissions($dir, $dirPerms, $filePerms);
                }
            }

            $this->info('✅ File permissions updated');
        } catch (Exception $e) {
            $this->warn('⚠️  Could not fix all permissions: ' . $e->getMessage());
        }
    }

    private function setDirectoryPermissions($directory, $dirPerms, $filePerms)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                chmod($item->getPathname(), $dirPerms);
            } else {
                chmod($item->getPathname(), $filePerms);
            }
        }
    }

    private function runHealthChecks()
    {
        $healthChecks = config('backup-complete-restore.health_checks', []);

        if (empty($healthChecks)) {
            return;
        }

        $this->info('🔍 Running health checks...');

        foreach ($healthChecks as $healthCheckClass) {
            if (class_exists($healthCheckClass)) {
                try {
                    $healthCheck = new $healthCheckClass();
                    if (method_exists($healthCheck, 'run')) {
                        $result = $healthCheck->run();
                        if ($result) {
                            $this->info("✅ {$healthCheckClass} passed");
                        } else {
                            $this->warn("⚠️  {$healthCheckClass} failed");
                        }
                    }
                } catch (Exception $e) {
                    $this->warn("⚠️  Health check {$healthCheckClass} error: " . $e->getMessage());
                }
            }
        }
    }

    private function listBackups()
    {
        $this->info('📋 Available Backups');
        $this->line('');

        $disks = ['local', 'google'];
        $backupName = config('backup.backup.name');

        foreach ($disks as $disk) {
            $this->info("💾 Disk: {$disk}");

            try {
                if (!Storage::disk($disk)->exists($backupName)) {
                    $this->line('   No backups found');
                    continue;
                }

                $files = Storage::disk($disk)->files($backupName);
                $backups = array_filter($files, fn($file) => str_ends_with($file, '.zip'));

                if (empty($backups)) {
                    $this->line('   No backup files found');
                    continue;
                }

                // Sort by date (newest first)
                usort($backups, fn($a, $b) => Storage::disk($disk)->lastModified($b) - Storage::disk($disk)->lastModified($a));

                foreach (array_slice($backups, 0, 10) as $backup) {
                    $size = $this->formatBytes(Storage::disk($disk)->size($backup));
                    $date = date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($backup));
                    $filename = basename($backup);
                    $this->line("   📁 {$filename} ({$size}) - {$date}");
                }

                if (count($backups) > 10) {
                    $this->line("   ... and " . (count($backups) - 10) . " more backups");
                }

            } catch (Exception $e) {
                $this->line("   Error accessing disk: " . $e->getMessage());
            }

            $this->line('');
        }

        $this->info('💡 To restore a specific backup:');
        $this->line('   php artisan backup:restore-complete --backup="filename.zip"');

        return 0;
    }

    private function cleanup($tempDir)
    {
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
            $this->info('🧹 Cleaned up temporary files');
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function dropAllTables()
    {
        try {
            $connection = $this->option('connection') ?: config('database.default');
            $db = DB::connection($connection);
            
            // Get all table names
            $tables = $db->select('SHOW TABLES');
            $tableNames = array_map(function($table) {
                return array_values((array) $table)[0];
            }, $tables);
            
            if (empty($tableNames)) {
                $this->info('ℹ️  No tables to drop');
                return true;
            }
            
            // Disable foreign key checks
            $db->statement('SET FOREIGN_KEY_CHECKS = 0');
            
            foreach ($tableNames as $table) {
                $this->line("🗑️  Dropping table: {$table}");
                $db->statement("DROP TABLE IF EXISTS `{$table}`");
            }
            
            // Re-enable foreign key checks
            $db->statement('SET FOREIGN_KEY_CHECKS = 1');
            
            $this->info('✅ All tables dropped successfully');
            return true;
            
        } catch (Exception $e) {
            $this->error('❌ Failed to drop tables: ' . $e->getMessage());
            return false;
        }
    }

    private function backupContainsDatabase($disk, $backupFile)
    {
        try {
            // Download and extract a small portion to check for database files
            $tempDir = storage_path('app/temp-check-' . time());
            File::makeDirectory($tempDir, 0755, true);
            
            $localBackupPath = $tempDir . '/backup-check.zip';
            $backupContent = Storage::disk($disk)->get($backupFile);
            
            if (!$backupContent) {
                File::deleteDirectory($tempDir);
                return false;
            }
            
            File::put($localBackupPath, $backupContent);
            
            // Extract just to check contents
            $zip = new ZipArchive;
            if ($zip->open($localBackupPath) !== TRUE) {
                File::deleteDirectory($tempDir);
                return false;
            }
            
            // Check if backup requires password
            $password = env('BACKUP_ARCHIVE_PASSWORD');
            if ($password) {
                $zip->setPassword($password);
            }
            
            // Look for database files in the archive
            $hasDatabase = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (str_ends_with($filename, '.sql') || str_contains($filename, 'db-dumps/')) {
                    $hasDatabase = true;
                    break;
                }
            }
            
            $zip->close();
            File::deleteDirectory($tempDir);
            
            return $hasDatabase;
            
        } catch (Exception $e) {
            if (isset($tempDir) && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            return false;
        }
    }

    private function importDatabaseDump($dumpFile, $connection)
    {
        try {
            $config = config("database.connections.{$connection}");
            
            if (!$config) {
                $this->error("❌ Database connection '{$connection}' not found");
                return false;
            }
            
            $this->info("📊 Importing to {$connection} database...");
            
            // Read the SQL file
            $sql = File::get($dumpFile);
            if (!$sql) {
                $this->error('❌ Failed to read SQL dump file');
                return false;
            }
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $db = DB::connection($connection);
            $total = count($statements);
            $current = 0;
            
            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                
                $current++;
                if ($current % 10 == 0) {
                    $this->line("⏳ Processing statement {$current}/{$total}...");
                }
                
                try {
                    $db->unprepared($statement);
                } catch (Exception $e) {
                    $this->warn("⚠️  Statement {$current} failed: " . substr($statement, 0, 50) . "...");
                    // Continue with other statements
                }
            }
            
            $this->info("✅ Database import completed ({$total} statements processed)");
            return true;
            
        } catch (Exception $e) {
            $this->error('❌ Database import failed: ' . $e->getMessage());
            return false;
        }
    }
}
