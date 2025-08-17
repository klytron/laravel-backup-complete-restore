<?php

namespace Klytron\LaravelBackupCompleteRestore\Commands;

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
        if ($zip->open($localBackupPath) !== TRUE) {
            $this->error('❌ Failed to open backup ZIP file');
            File::deleteDirectory($tempDir);
            return null;
        }
        
        // Check if backup requires password
        $password = env('BACKUP_ARCHIVE_PASSWORD');
        if (!$password) {
            // Try alternative methods to get the password
            $password = config('backup.backup.password');
        }
        if (!$password) {
            // Try reading directly from .env file
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                if (preg_match('/BACKUP_ARCHIVE_PASSWORD=(.+)/', $envContent, $matches)) {
                    $password = trim($matches[1]);
                    // Remove quotes if present
                    $password = trim($password, '"\'');
                }
            }
        }
        
        if ($password) {
            $zip->setPassword($password);
            $this->info('🔐 Using configured backup password');
        } else {
            $this->warn('⚠️  No backup password found - trying without password');
        }
        
                    if ($zip->extractTo($tempDir) === TRUE) {
                $zip->close();
                
                // Remove the zip file
                File::delete($localBackupPath);
                
                $this->info('✅ Backup extracted successfully');
                
                // Debug: Show what's in the extracted backup
                $this->info('📁 Backup contents:');
                $this->listBackupContents($tempDir);
                
                return $tempDir;
            } else {
                $zip->close();
                $this->error('❌ Failed to extract backup ZIP file (check password if encrypted)');
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
            if (!$password) {
                // Try alternative methods to get the password
                $password = config('backup.backup.password');
            }
            if (!$password) {
                // Try reading directly from .env file
                $envPath = base_path('.env');
                if (file_exists($envPath)) {
                    $envContent = file_get_contents($envPath);
                    if (preg_match('/BACKUP_ARCHIVE_PASSWORD=(.+)/', $envContent, $matches)) {
                        $password = trim($matches[1]);
                        // Remove quotes if present
                        $password = trim($password, '"\'');
                    }
                }
            }
            
            if ($password) {
                $zip->setPassword($password);
                $this->info('🔐 Using configured backup password');
            } else {
                $this->warn('⚠️  No backup password found - trying without password');
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

        try {
            // Look for the storage directory in the backup
            $storagePath = $this->findStoragePathInBackup($tempDir);
            
            if (!$storagePath) {
                $this->error('❌ Storage directory not found in backup');
                $this->warn('💡 Available directories in backup:');
                $this->listBackupContents($tempDir, 2);
                return false;
            }

            $this->info("📁 Found storage directory: " . basename($storagePath));
            
            // Restore storage directory to the current storage path
            $targetStoragePath = storage_path();
            
            $this->info("🔄 Restoring storage from: " . basename($storagePath));
            $this->info("🔄 Restoring storage to: " . $targetStoragePath);
            
            if ($this->restoreDirectory($storagePath, $targetStoragePath)) {
                $this->info("✅ Restored storage directory");
                $restored++;
            } else {
                $this->error("❌ Failed to restore storage directory");
                $failed++;
            }

            // Also try to restore public directories if they exist
            $publicPaths = $this->findPublicPathsInBackup($tempDir);
            foreach ($publicPaths as $publicPath) {
                $this->info("🔄 Restoring public directory: " . basename($publicPath));
                if ($this->restorePublicDirectory($publicPath)) {
                    $this->info("✅ Restored public directory: " . basename($publicPath));
                    $restored++;
                } else {
                    $this->warn("⚠️  Failed to restore public directory: " . basename($publicPath));
                    $failed++;
                }
            }

            // Fix permissions after restoration
            if ($restored > 0) {
                $this->info('🔧 Fixing file permissions...');
                $this->fixPermissions();
            }

            $this->info("📊 File restoration completed: {$restored} successful, {$failed} failed");
            return $failed === 0;
            
        } catch (Exception $e) {
            $this->error('❌ File restoration failed with error: ' . $e->getMessage());
            return false;
        }
    }

    private function findStoragePathInBackup($tempDir)
    {
        // Look for storage directory in common locations
        $possiblePaths = [
            $tempDir . '/var/www/html/storage',
            $tempDir . '/storage',
            $tempDir . '/app/storage',
            $tempDir . '/app/public/storage',
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path) && File::isDirectory($path)) {
                $this->info("✅ Found storage directory: " . basename($path));
                return $path;
            }
        }

        // If not found in common locations, search recursively
        $this->info('🔍 Searching for storage directory in backup...');
        $storagePath = $this->findDirectoryRecursively($tempDir, 'storage');
        
        if ($storagePath) {
            $this->info("✅ Found storage directory recursively: " . basename($storagePath));
        } else {
            $this->warn("⚠️  Storage directory not found in common locations");
        }
        
        return $storagePath;
    }

    private function findDirectoryRecursively($dir, $targetDir)
    {
        $items = File::directories($dir);
        
        foreach ($items as $item) {
            $basename = basename($item);
            if ($basename === $targetDir) {
                return $item;
            }
            
            $found = $this->findDirectoryRecursively($item, $targetDir);
            if ($found) {
                return $found;
            }
        }
        
        return null;
    }

    private function findPublicPathsInBackup($tempDir)
    {
        $publicPaths = [];
        
        // Look for public directories in common locations
        $possiblePaths = [
            $tempDir . '/var/www/html/public',
            $tempDir . '/public',
            $tempDir . '/app/public',
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path) && File::isDirectory($path)) {
                $publicPaths[] = $path;
            }
        }

        // If not found in common locations, search recursively
        if (empty($publicPaths)) {
            $this->info('🔍 Searching for public directories in backup...');
            $publicPath = $this->findDirectoryRecursively($tempDir, 'public');
            if ($publicPath) {
                $publicPaths[] = $publicPath;
            }
        }
        
        return $publicPaths;
    }

    private function restorePublicDirectory($publicPath)
    {
        try {
            $targetPublicPath = public_path();
            
            // Check if this is a ShynDorca TP project (has uploads/download directories)
            $hasUploads = File::exists($publicPath . '/uploads');
            $hasDownload = File::exists($publicPath . '/download');
            
            if ($hasUploads || $hasDownload) {
                $this->info("📁 Detected ShynDorca TP public directory");
                
                // Restore specific directories that are backed up
                if ($hasUploads) {
                    $this->info("🔄 Restoring uploads directory...");
                    $this->copyDirectoryContents($publicPath . '/uploads', $targetPublicPath . '/uploads');
                }
                
                if ($hasDownload) {
                    $this->info("🔄 Restoring download directory...");
                    $this->copyDirectoryContents($publicPath . '/download', $targetPublicPath . '/download');
                }
                
                return true;
            } else {
                // For other projects, restore the entire public directory
                $this->info("🔄 Restoring entire public directory...");
                return $this->restoreDirectory($publicPath, $targetPublicPath);
            }
            
        } catch (Exception $e) {
            $this->error("❌ Failed to restore public directory: " . $e->getMessage());
            return false;
        }
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

    private function listBackupContents($tempDir, $maxDepth = 3)
    {
        $this->listDirectoryContents($tempDir, '', $maxDepth);
    }

    private function listDirectoryContents($dir, $prefix = '', $maxDepth = 3, $currentDepth = 0)
    {
        if ($currentDepth >= $maxDepth) {
            $this->line($prefix . '└── ... (max depth reached)');
            return;
        }

        $items = File::files($dir);
        $directories = File::directories($dir);
        
        $allItems = array_merge($directories, $items);
        
        foreach ($allItems as $index => $item) {
            $isLast = ($index === count($allItems) - 1);
            $symbol = $isLast ? '└── ' : '├── ';
            $name = basename($item);
            
            if (File::isDirectory($item)) {
                $this->line($prefix . $symbol . $name . '/');
                if ($currentDepth < $maxDepth - 1) {
                    $this->listDirectoryContents($item, $prefix . ($isLast ? '    ' : '│   '), $maxDepth, $currentDepth + 1);
                }
            } else {
                $size = $this->formatBytes(File::size($item));
                $this->line($prefix . $symbol . $name . ' (' . $size . ')');
            }
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
            $this->info("📊 Database: {$config['database']} on {$config['host']}:{$config['port']}");
            
            // Read the SQL file
            $sql = File::get($dumpFile);
            if (!$sql) {
                $this->error('❌ Failed to read SQL dump file');
                return false;
            }
            
            $this->info("📊 SQL file size: " . $this->formatBytes(strlen($sql)));
            
            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $db = DB::connection($connection);
            $total = count($statements);
            $current = 0;
            $errors = 0;
            
            $this->info("📊 Processing {$total} SQL statements...");
            
            foreach ($statements as $statement) {
                if (empty($statement)) continue;
                
                $current++;
                if ($current % 50 == 0) {
                    $this->line("⏳ Processing statement {$current}/{$total}... (errors: {$errors})");
                }
                
                try {
                    $db->unprepared($statement);
                } catch (Exception $e) {
                    $errors++;
                    if ($errors <= 5) { // Only show first 5 errors
                        $this->warn("⚠️  Statement {$current} failed: " . substr($statement, 0, 100) . "...");
                        $this->warn("   Error: " . $e->getMessage());
                    }
                    // Continue with other statements
                }
            }
            
            if ($errors > 0) {
                $this->warn("⚠️  Database import completed with {$errors} errors out of {$total} statements");
            } else {
                $this->info("✅ Database import completed successfully ({$total} statements processed)");
            }
            
            return $errors < $total; // Return true if at least some statements succeeded
            
        } catch (Exception $e) {
            $this->error('❌ Database import failed: ' . $e->getMessage());
            return false;
        }
    }
}
