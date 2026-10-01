<?php

namespace Klytron\LaravelBackupCompleteRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                            {--connection= : Database connection to restore to (defaults to database.default)}
                            {--reset : Drop all tables before restoring}
                            {--database-only : Restore only database}
                            {--files-only : Restore only files}
                            {--list : List available backups}
                            {--dry-run : List what would be restored without restoring anything}
                            {--keep-temp : Keep the temporary extraction directory for debugging}
                            {--skip-verification : Skip post-restore sqlite verification}
                            {--boot-probe : Run a boot probe after sqlite verification}
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

        // Safety warnings (a dry run restores nothing, so no confirmation needed)
        if (!$this->option('force') && !$this->option('dry-run')) {
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
        $connection = $this->option('connection') ?: config('database.default');
        $tempDir = null;

        // Apply execution time and memory limits if configured
        if ($maxExecTime = config('backup-complete-restore.restoration.max_execution_time', 3600)) {
            @ini_set('max_execution_time', (string) $maxExecTime);
        }
        if ($memoryLimit = config('backup-complete-restore.restoration.memory_limit', '512M')) {
            @ini_set('memory_limit', (string) $memoryLimit);
        }

        try {
            // Find the backup file
            $backupFile = $this->findBackupFile($disk, $backup);
            if (!$backupFile) {
                $this->error('❌ Backup file not found!');
                return 1;
            }

            $this->info("📁 Using backup: " . basename($backupFile));
            $this->line('');

            // Dry run: report what would be restored, restore nothing.
            if ($this->option('dry-run')) {
                return $this->dryRun($disk, $backupFile, $connection, $databaseOnly, $filesOnly);
            }

            // Extract backup to temporary location once (shared for DB and files)
            $tempDir = $this->extractBackup($disk, $backupFile);
            if (!$tempDir) {
                $this->error('❌ Failed to extract backup!');
                return 1;
            }

            $success = true;

            // Check if this backup contains database dumps
            $dbFiles = $this->findDatabaseDumpsInExtracted($tempDir);
            $hasDatabase = !empty($dbFiles);
            
            // Restore database first (if backup contains database and not files-only)
            if (!$filesOnly && $hasDatabase) {
                $this->info('🗄️  Restoring database...');
                if (!$this->restoreDatabaseFromDump($dbFiles[0])) {
                    $success = false;
                } elseif (!$this->option('skip-verification')
                    && !$this->verifySqliteRestore($connection)
                ) {
                    $success = false;
                }
            } elseif (!$filesOnly && !$hasDatabase) {
                $this->info('ℹ️  No database found in backup (files-only backup)');
            }

            // Restore files (if not database-only)
            if (!$databaseOnly && $success) {
                $this->info('📁 Restoring files...');
                if (!$this->restoreFiles($tempDir)) {
                    $success = false;
                }
            }

            if ($success) {
                // Run post-restoration health checks if configured
                if (config('backup-complete-restore.restoration.run_health_checks', true)) {
                    $this->runHealthChecks();
                }

                // Automatically clear caches if configured
                if (config('backup-complete-restore.restoration.clear_caches', false)) {
                    $this->info('🧹 Clearing application caches...');
                    try {
                        Artisan::call('cache:clear');
                        Artisan::call('config:clear');
                        $this->info('✅ Application caches cleared');
                    } catch (Exception $e) {
                        $this->warn('⚠️  Could not clear caches: ' . $e->getMessage());
                    }
                }

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
        } finally {
            if ($tempDir && File::exists($tempDir) && config('backup-complete-restore.cleanup_temp_files', true)) {
                if ($this->option('keep-temp')) {
                    $this->line("🧊 Kept temp dir for debugging: {$tempDir}");
                } else {
                    $this->cleanup($tempDir);
                }
            }
        }
    }

    private function findBackupFile($disk, $backup = null)
    {
        $backupName = config('backup.backup.name');
        if (!$backupName) {
            return null;
        }
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
        $tempDir = null;
        try {
            $tempBase = config('backup-complete-restore.temp_directory', storage_path('app/temp-restore'));
            $tempDir = $tempBase . '-' . time() . '-' . uniqid();
            File::makeDirectory($tempDir, 0755, true);

            // Download backup file to temp location
            $localBackupPath = $tempDir . '/backup.zip';
            $this->info('⏳ Downloading backup from ' . $disk . ' disk...');

            // Stream download if possible to avoid high memory consumption on large archives
            $readStream = Storage::disk($disk)->readStream($backupFile);
            if ($readStream) {
                $writeStream = fopen($localBackupPath, 'wb');
                stream_copy_to_stream($readStream, $writeStream);
                fclose($readStream);
                fclose($writeStream);
            } else {
                $backupContent = Storage::disk($disk)->get($backupFile);
                if (!$backupContent) {
                    $this->error('❌ Failed to download backup file from ' . $disk . ' disk');
                    if (File::exists($tempDir)) {
                        File::deleteDirectory($tempDir);
                    }
                    return null;
                }
                File::put($localBackupPath, $backupContent);
            }

            $this->info('✅ Backup file downloaded successfully (' . $this->formatBytes(File::size($localBackupPath)) . ')');

            // Extract ZIP file
            $this->info('📦 Extracting backup archive...');
            $zip = new ZipArchive;
            if ($zip->open($localBackupPath) !== TRUE) {
                $this->error('❌ Failed to open backup ZIP file');
                if (File::exists($tempDir)) {
                    File::deleteDirectory($tempDir);
                }
                return null;
            }
            
            // Check if backup requires password
            $password = $this->getBackupPassword();
            if ($password) {
                $zip->setPassword($password);
                $this->info('🔐 Using configured backup password');
            } else {
                $this->warn('⚠️  No backup password found - trying without password');
            }
            
            if ($this->extractWithProgress($zip, $tempDir)) {
                $zip->close();
                
                // Remove the zip file to save disk space
                File::delete($localBackupPath);

                $this->info('✅ Backup extracted successfully');
                
                // Debug: Show what's in the extracted backup
                $this->info('📁 Backup contents:');
                $this->listBackupContents($tempDir);
                
                return $tempDir;
            } else {
                $zip->close();
                if (File::exists($tempDir)) {
                    File::deleteDirectory($tempDir);
                }
                return null;
            }
        } catch (Exception $e) {
            $this->error('❌ Failed to extract backup: ' . $e->getMessage());
            if ($tempDir && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            return null;
        }
    }

    /**
     * Extract every archive entry one at a time, streaming progress output
     * (file count / bytes) so long restores don't look hung.
     *
     * @param ZipArchive $zip Opened archive (password already set if needed)
     * @param string $tempDir Extraction target directory
     * @return bool True when at least one entry extracted and none failed
     */
    private function extractWithProgress(ZipArchive $zip, string $tempDir): bool
    {
        $total = $zip->numFiles;

        if ($total === 0) {
            $this->warn('⚠️  Backup archive is empty');
            return true;
        }

        $this->info("📦 Extracting {$total} files...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $bytes = 0;
        $failed = 0;

        for ($i = 0; $i < $total; $i++) {
            $name = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);

            if ($name === false || $zip->extractTo($tempDir, $name) !== true) {
                $failed++;
            } else {
                $bytes += $stat['size'] ?? 0;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info("📊 Extracted " . ($total - $failed) . "/{$total} files (" . $this->formatBytes($bytes) . ")");

        if ($failed > 0) {
            $this->error("❌ Failed to extract {$failed} file(s) (check password if encrypted)");
            return false;
        }

        return true;
    }

    /**
     * Dry run: download the archive, inspect it, and report what a restore
     * WOULD do (targets, database/files plan) without restoring anything.
     */
    private function dryRun(string $disk, string $backupFile, string $connection, bool $databaseOnly, bool $filesOnly): int
    {
        $this->info('🔍 Dry run — nothing will be restored');
        $this->line('');

        $tmpPath = tempnam(sys_get_temp_dir(), 'dry-run-backup-') . '.zip';

        try {
            $readStream = Storage::disk($disk)->readStream($backupFile);
            if ($readStream) {
                $writeStream = fopen($tmpPath, 'wb');
                stream_copy_to_stream($readStream, $writeStream);
                fclose($readStream);
                fclose($writeStream);
            } else {
                $content = Storage::disk($disk)->get($backupFile);
                if (!$content) {
                    $this->error('❌ Failed to download backup file from ' . $disk . ' disk');
                    return 1;
                }
                File::put($tmpPath, $content);
            }

            $zip = new ZipArchive;
            if ($zip->open($tmpPath) !== true) {
                $this->error('❌ Failed to open backup ZIP file');
                return 1;
            }

            $password = $this->getBackupPassword();
            if ($password) {
                $zip->setPassword($password);
            }

            $entries = $zip->numFiles;
            $uncompressed = 0;
            $dbDumps = [];
            $hasStorage = false;
            $hasPublic = false;

            for ($i = 0; $i < $entries; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $stat = $zip->statIndex($i);
                $uncompressed += $stat['size'] ?? 0;

                if (str_ends_with($name, '.sql') || str_contains($name, 'db-dumps/')) {
                    $dbDumps[] = $name;
                }
                if (!$hasStorage && str_contains($name, 'storage/')) {
                    $hasStorage = true;
                }
                if (!$hasPublic && str_contains($name, 'public/')) {
                    $hasPublic = true;
                }
            }

            $zip->close();

            $date = date('Y-m-d H:i:s', Storage::disk($disk)->lastModified($backupFile));

            $this->info('📦 Archive: ' . basename($backupFile));
            $this->line("   Date: {$date}");
            $this->line('   Compressed size: ' . $this->formatBytes(File::size($tmpPath)));
            $this->line("   Contents: {$entries} entries (" . $this->formatBytes($uncompressed) . ' uncompressed)');
            $this->line('   Database dumps: ' . (empty($dbDumps) ? 'none' : implode(', ', array_slice($dbDumps, 0, 10))));
            $this->line('');

            $this->info('📋 Restore plan:');
            if (!$filesOnly && !empty($dbDumps)) {
                $this->line("   • Database → connection '{$connection}' (" . count($dbDumps) . ' dump(s))');
            } elseif (!$filesOnly) {
                $this->line('   • Database → skipped (no dumps in archive)');
            }
            if (!$databaseOnly && $hasStorage) {
                $this->line('   • Storage files → ' . storage_path());
            } elseif (!$databaseOnly && !$hasPublic) {
                $this->line('   • Files → skipped (no storage/public dirs detected in archive)');
            }
            if (!$databaseOnly && $hasPublic) {
                $this->line('   • Public files → ' . public_path());
            }
            $this->line('');
            $this->info('💡 Run without --dry-run to perform this restore.');

            return 0;
        } catch (Exception $e) {
            $this->error('❌ Dry run failed: ' . $e->getMessage());
            return 1;
        } finally {
            if (File::exists($tmpPath)) {
                File::delete($tmpPath);
            }
        }
    }

    /**
     * Post-restore verification for sqlite drivers: the restore is a file
     * replace, so run PRAGMA integrity_check on the restored file, an
     * optional boot probe, and print a verification summary line.
     *
     * Non-sqlite drivers have nothing file-level to check: no-op success.
     */
    private function verifySqliteRestore(string $connection): bool
    {
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'sqlite') {
            return true;
        }

        if (!config('backup-complete-restore.restoration.verify_sqlite', true)) {
            return true;
        }

        $this->info('🔍 Verifying sqlite restore...');

        try {
            $db = DB::connection($connection);
            $rows = $db->select('PRAGMA integrity_check');
            $values = array_map(
                fn ($row) => strtolower(trim((string) (array_values((array) $row)[0] ?? ''))),
                $rows
            );
            $integrity = (!empty($values) && count(array_unique($values)) === 1 && $values[0] === 'ok')
                ? 'ok'
                : 'FAILED';

            if ($integrity !== 'ok') {
                $detail = implode('; ', array_slice($values, 0, 5));
                $this->error("❌ SQLite integrity_check FAILED: {$detail}");
                $this->line("🔍 SQLite verification: integrity_check=FAILED, boot probe=skipped");
                return false;
            }

            $this->info('✅ SQLite integrity_check: ok');

            $probe = 'skipped';
            if ($this->option('boot-probe') || config('backup-complete-restore.restoration.sqlite_boot_probe', false)) {
                try {
                    DB::purge($connection);
                    DB::connection($connection)->select('SELECT 1');
                    $configuredCommand = config('backup-complete-restore.restoration.boot_probe_command');
                    if (is_string($configuredCommand) && $configuredCommand !== '') {
                        Artisan::call($configuredCommand);
                    }
                    $probe = 'passed';
                    $this->info('✅ Boot probe: passed');
                } catch (Exception $e) {
                    $probe = 'FAILED';
                    $this->error('❌ Boot probe FAILED: ' . $e->getMessage());
                    $this->line("🔍 SQLite verification: integrity_check=ok, boot probe=FAILED");
                    return false;
                }
            }

            $this->line("🔍 SQLite verification: integrity_check=ok, boot probe={$probe}");

            return true;
        } catch (Exception $e) {
            $this->error('❌ SQLite verification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function findDatabaseDumpsInExtracted($tempDir): array
    {
        $allFiles = File::allFiles($tempDir);
        $dbFiles = [];
        foreach ($allFiles as $file) {
            if ($file->getExtension() === 'sql' || str_ends_with($file->getFilename(), '.sql')) {
                $dbFiles[] = $file->getRealPath();
            }
        }
        return $dbFiles;
    }

    private function restoreDatabaseFromDump($dbFile): bool
    {
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
            return true;
        } else {
            $this->error('❌ Database restore failed');
            return false;
        }
    }

    private function restoreDatabase($disk, $backupFile)
    {
        $tempDir = null;
        try {
            $tempDir = $this->extractBackup($disk, $backupFile);
            if (!$tempDir) {
                return false;
            }
            $dbFiles = $this->findDatabaseDumpsInExtracted($tempDir);
            if (empty($dbFiles)) {
                $this->error('❌ No SQL dump file found in backup');
                return false;
            }
            return $this->restoreDatabaseFromDump($dbFiles[0]);
        } catch (Exception $e) {
            $this->error('❌ Database restore error: ' . $e->getMessage());
            return false;
        } finally {
            if ($tempDir && File::exists($tempDir) && config('backup-complete-restore.cleanup_temp_files', true)) {
                if ($this->option('keep-temp')) {
                    $this->line("🧊 Kept temp dir for debugging: {$tempDir}");
                } else {
                    $this->cleanup($tempDir);
                }
            }
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
                } catch (\Throwable $e) {
                    // A broken health check must never fail an otherwise good restore
                    // (e.g. upstream checks whose run() signature requires arguments).
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

        if (!$backupName) {
            $this->warn('⚠️  Spatie backup name is not configured (backup.backup.name).');
            return 0;
        }

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

    /**
     * Get backup password from various sources.
     * Checks env, config, and .env file in order.
     *
     * @return string|null The password or null if not found
     */
    private function getBackupPassword()
    {
        // Try env first
        $password = env('BACKUP_ARCHIVE_PASSWORD');
        if ($password) {
            return $password;
        }

        // Try config
        $password = config('backup.backup.password');
        if ($password) {
            return $password;
        }

        // Try reading directly from .env file (for cases where env is cached)
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            // Use [^\r\n]+ to stop at end of line, preventing grabbing rest of file
            if (preg_match('/BACKUP_ARCHIVE_PASSWORD=([^\r\n]+)/', $envContent, $matches)) {
                $password = trim($matches[1]);
                // Remove quotes if present
                $password = trim($password, '"\'');
                if (!empty($password)) {
                    return $password;
                }
            }
        }

        return null;
    }

    private function dropAllTables()
    {
        try {
            $connection = $this->option('connection') ?: config('database.default');
            $db = DB::connection($connection);
            $driver = $db->getDriverName();

            // First attempt: Laravel SchemaBuilder dropAllTables if available
            try {
                Schema::connection($connection)->dropAllTables();
                $this->info('✅ All tables dropped successfully via Schema');
                return true;
            } catch (Exception $schemaEx) {
                // Fallback to driver-specific SQL execution if Schema drop fails
            }

            if ($driver === 'sqlite') {
                $tables = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $db->statement('PRAGMA foreign_keys = OFF');
                foreach ($tables as $table) {
                    $tableName = $table->name ?? array_values((array) $table)[0];
                    $this->line("🗑️  Dropping table: {$tableName}");
                    $db->statement("DROP TABLE IF EXISTS \"{$tableName}\"");
                }
                $db->statement('PRAGMA foreign_keys = ON');
            } elseif ($driver === 'pgsql') {
                $tables = $db->select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
                foreach ($tables as $table) {
                    $tableName = $table->tablename ?? array_values((array) $table)[0];
                    $this->line("🗑️  Dropping table: {$tableName}");
                    $db->statement("DROP TABLE IF EXISTS \"{$tableName}\" CASCADE");
                }
            } else {
                // MySQL / MariaDB / Default
                $tables = $db->select('SHOW TABLES');
                $tableNames = array_map(function($table) {
                    return array_values((array) $table)[0];
                }, $tables);

                if (empty($tableNames)) {
                    $this->info('ℹ️  No tables to drop');
                    return true;
                }

                $db->statement('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($tableNames as $table) {
                    $this->line("🗑️  Dropping table: {$table}");
                    $escapedTable = str_replace('`', '``', $table);
                    $db->statement("DROP TABLE IF EXISTS `{$escapedTable}`");
                }
                $db->statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $this->info('✅ All tables dropped successfully');
            return true;

        } catch (Exception $e) {
            $this->error('❌ Failed to drop tables: ' . $e->getMessage());
            return false;
        }
    }

    private function backupContainsDatabase($disk, $backupFile)
    {
        $tempDir = null;
        try {
            // Download and extract a small portion to check for database files
            $tempDir = storage_path('app/temp-check-' . time() . '-' . uniqid());
            File::makeDirectory($tempDir, 0755, true);
            
            $localBackupPath = $tempDir . '/backup-check.zip';
            $backupContent = Storage::disk($disk)->get($backupFile);
            
            if (!$backupContent) {
                return false;
            }
            
            File::put($localBackupPath, $backupContent);
            
            // Extract just to check contents
            $zip = new ZipArchive;
            if ($zip->open($localBackupPath) !== TRUE) {
                return false;
            }
            
            // Check if backup requires password
            $password = $this->getBackupPassword();
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
            return $hasDatabase;
            
        } catch (Exception $e) {
            return false;
        } finally {
            if ($tempDir && File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
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

            $driver = $config['driver'] ?? 'unknown';
            $hostInfo = isset($config['host']) ? " on {$config['host']}" . (isset($config['port']) ? ":{$config['port']}" : "") : "";
            $databaseName = $config['database'] ?? $connection;

            $this->info("📊 Importing to {$connection} database ({$driver})...");
            $this->info("📊 Database: {$databaseName}{$hostInfo}");
            
            // Check file size to determine reading method
            $fileSize = File::size($dumpFile);
            $this->info("📊 SQL file size: " . $this->formatBytes($fileSize));
            
            $db = DB::connection($connection);
            $totalStatements = 0;
            $processedStatements = 0;
            $errors = 0;
            
            // Use streaming for files larger than 10MB
            $useStreaming = $fileSize > 10 * 1024 * 1024;
            
            // Disable foreign key checks during import
            try {
                if ($driver === 'sqlite') {
                    $db->statement('PRAGMA foreign_keys = OFF');
                } elseif (in_array($driver, ['mysql', 'mariadb'])) {
                    $db->statement('SET FOREIGN_KEY_CHECKS = 0');
                }
            } catch (Exception $fkEx) {
                // Ignore if not supported
            }

            try {
                if ($useStreaming) {
                    $this->info("📊 Using streaming mode for large file...");
                    $result = $this->importSqlStreaming($dumpFile, $db);
                    $totalStatements = $result['total'];
                    $errors = $result['errors'];
                    $processedStatements = $totalStatements - $errors;
                } else {
                    // Read the entire SQL file for smaller files
                    $sql = File::get($dumpFile);
                    if (!$sql) {
                        $this->error('❌ Failed to read SQL dump file');
                        return false;
                    }
                    
                    // Parse SQL statements respecting string literals and escaped characters
                    $statements = $this->parseSqlStatements($sql);
                    
                    if (empty($statements)) {
                        $this->error('❌ No valid SQL statements found in dump file');
                        return false;
                    }
                    
                    $totalStatements = count($statements);
                    $this->info("📊 Processing {$totalStatements} SQL statements...");
                    
                    foreach ($statements as $statement) {
                        if (empty($statement)) continue;
                        
                        $processedStatements++;
                        if ($processedStatements % 50 == 0) {
                            $this->line("⏳ Processing statement {$processedStatements}/{$totalStatements}... (errors: {$errors})");
                        }
                        
                        try {
                            $db->unprepared($statement);
                        } catch (Exception $e) {
                            $errors++;
                            if ($errors <= 5) { // Only show first 5 errors
                                $this->warn("⚠️  Statement {$processedStatements} failed: " . substr($statement, 0, 100) . "...");
                                $this->warn("   Error: " . $e->getMessage());
                            }
                            // Continue with other statements
                        }
                    }
                }
            } finally {
                // Re-enable foreign key checks
                try {
                    if ($driver === 'sqlite') {
                        $db->statement('PRAGMA foreign_keys = ON');
                    } elseif (in_array($driver, ['mysql', 'mariadb'])) {
                        $db->statement('SET FOREIGN_KEY_CHECKS = 1');
                    }
                } catch (Exception $fkEx) {
                    // Ignore
                }
            }
            
            if ($errors > 0) {
                $this->warn("⚠️  Database import completed with {$errors} errors out of {$totalStatements} statements");
            } else {
                $this->info("✅ Database import completed successfully ({$totalStatements} statements processed)");
            }
            
            return $errors < $totalStatements; // Return true if at least some statements succeeded
            
        } catch (Exception $e) {
            $this->error('❌ Database import failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Import large SQL files using streaming/chunked reading.
     * This prevents memory exhaustion with very large dumps.
     *
     * @param string $dumpFile Path to the SQL dump file
     * @param mixed $db Database connection
     * @return array Array with 'total' and 'errors' counts
     */
    private function importSqlStreaming($dumpFile, $db)
    {
        $handle = fopen($dumpFile, 'r');
        if (!$handle) {
            throw new Exception('Failed to open SQL file for streaming');
        }

        $buffer = '';
        $total = 0;
        $errors = 0;
        $chunkSize = 1024 * 1024; // 1MB chunks

        $this->info("📊 Processing SQL file in chunks...");

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $buffer .= $chunk;

            // Process complete statements from buffer
            $result = $this->processBuffer($buffer, $db);
            $total += $result['processed'];
            $errors += $result['errors'];
            $buffer = $result['remainder'];

            // Progress update every 50 statements
            if ($total % 50 == 0) {
                $this->line("⏳ Processed {$total} statements... (errors: {$errors})");
            }
        }

        // Process any remaining statements in buffer
        if (!empty($buffer)) {
            $result = $this->processBuffer($buffer . ';', $db); // Add semicolon to force final statement
            $total += $result['processed'];
            $errors += $result['errors'];
        }

        fclose($handle);

        return ['total' => $total, 'errors' => $errors];
    }

    /**
     * Process SQL buffer and extract complete statements.
     *
     * @param string $buffer SQL buffer content
     * @param mixed $db Database connection
     * @return array Array with 'processed', 'errors', and 'remainder'
     */
    private function processBuffer(&$buffer, $db)
    {
        $processed = 0;
        $errors = 0;
        $remainder = '';

        // Parse statements from buffer
        $statements = $this->parseSqlStatements($buffer);

        if (empty($statements)) {
            return ['processed' => 0, 'errors' => 0, 'remainder' => $buffer];
        }

        // Check if the last statement might be incomplete (no semicolon at end)
        $lastStatement = end($statements);
        $bufferEndsWithSemicolon = substr(rtrim($buffer), -1) === ';';

        // If buffer doesn't end with semicolon, the last statement is likely incomplete
        if (!$bufferEndsWithSemicolon && count($statements) > 0) {
            $remainder = array_pop($statements);
        }

        // Execute complete statements
        foreach ($statements as $statement) {
            if (empty($statement)) continue;

            $processed++;

            try {
                $db->unprepared($statement);
            } catch (Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->warn("⚠️  Statement failed: " . substr($statement, 0, 100) . "...");
                    $this->warn("   Error: " . $e->getMessage());
                }
            }
        }

        return ['processed' => $processed, 'errors' => $errors, 'remainder' => $remainder];
    }

    /**
     * Parse SQL statements respecting string literals, escaped characters, and comments.
     * This prevents issues with semicolons inside string data.
     *
     * @param string $sql The SQL dump content
     * @return array Array of individual SQL statements
     */
    private function parseSqlStatements($sql)
    {
        $statements = [];
        $currentStatement = '';
        $length = strlen($sql);
        $inString = false;
        $stringChar = null;
        $escapeNext = false;
        $inComment = false;
        $commentType = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $nextChar = $i + 1 < $length ? $sql[$i + 1] : null;

            // Handle escape sequences
            if ($escapeNext) {
                $currentStatement .= $char;
                $escapeNext = false;
                continue;
            }

            // Check for escape character
            if ($char === '\\' && $inString) {
                $currentStatement .= $char;
                $escapeNext = true;
                continue;
            }

            // Handle SQL comments (-- and /* */)
            if (!$inString && !$inComment) {
                // Single line comment --
                if ($char === '-' && $nextChar === '-') {
                    $inComment = true;
                    $commentType = 'single';
                    $currentStatement .= $char;
                    continue;
                }
                // Multi-line comment /*
                if ($char === '/' && $nextChar === '*') {
                    $inComment = true;
                    $commentType = 'multi';
                    $currentStatement .= $char;
                    continue;
                }
            } elseif ($inComment) {
                $currentStatement .= $char;
                if ($commentType === 'single' && $char === "\n") {
                    $inComment = false;
                    $commentType = null;
                } elseif ($commentType === 'multi' && $char === '*' && $nextChar === '/') {
                    $currentStatement .= $nextChar;
                    $i++; // Skip the /
                    $inComment = false;
                    $commentType = null;
                }
                continue;
            }

            // Handle string literals (both single and double quotes)
            if (!$inComment) {
                if ($char === "'" || $char === '"') {
                    if (!$inString) {
                        $inString = true;
                        $stringChar = $char;
                    } elseif ($stringChar === $char) {
                        // Check for doubled quotes (SQL escaping style: '')
                        if ($nextChar === $char) {
                            $currentStatement .= $char . $nextChar;
                            $i++; // Skip next quote
                            continue;
                        }
                        $inString = false;
                        $stringChar = null;
                    }
                    $currentStatement .= $char;
                    continue;
                }
            }

            // Statement terminator (semicolon outside of strings and comments)
            if ($char === ';' && !$inString && !$inComment) {
                $trimmed = trim($currentStatement);
                if (!empty($trimmed)) {
                    $statements[] = $trimmed;
                }
                $currentStatement = '';
                continue;
            }

            $currentStatement .= $char;
        }

        // Add final statement if any
        $trimmed = trim($currentStatement);
        if (!empty($trimmed)) {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
