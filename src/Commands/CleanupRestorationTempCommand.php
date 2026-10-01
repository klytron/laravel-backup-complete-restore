<?php

namespace Klytron\LaravelBackupCompleteRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Exception;

class CleanupRestorationTempCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klytron:backup:cleanup:restoration
                            {--dry-run : List stale temp directories without deleting them}
                            {--older-than=60 : Only purge directories older than N minutes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge stale backup restoration temp directories (temp-restore-*, temp-check-*)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $olderThanMinutes = max(0, (int) $this->option('older-than'));
        $dryRun = (bool) $this->option('dry-run');

        $candidates = $this->staleTempDirs($olderThanMinutes);

        if (empty($candidates)) {
            $this->info('✅ No stale restoration temp directories found.');
            return 0;
        }

        $this->info('🧹 Stale restoration temp directories:');
        foreach ($candidates as $dir) {
            $size = $this->formatBytes($this->directorySize($dir));
            $modified = date('Y-m-d H:i:s', File::lastModified($dir));
            $this->line("   📁 {$dir} ({$size}, modified {$modified})");
        }

        if ($dryRun) {
            $this->info('🔍 Dry run — nothing deleted. Re-run without --dry-run to purge.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm('Delete these directories?')) {
            $this->info('❌ Cleanup cancelled.');
            return 1;
        }

        $deleted = 0;
        foreach ($candidates as $dir) {
            try {
                File::deleteDirectory($dir);
                $deleted++;
            } catch (Exception $e) {
                $this->warn("⚠️  Could not delete {$dir}: " . $e->getMessage());
            }
        }

        $this->info("✅ Purged {$deleted}/" . count($candidates) . ' stale temp directories.');

        return $deleted === count($candidates) ? 0 : 1;
    }

    /**
     * Find restoration temp directories older than the given age.
     *
     * @param int $olderThanMinutes Minimum age in minutes
     * @return array List of absolute directory paths, oldest first
     */
    public function staleTempDirs(int $olderThanMinutes = 60): array
    {
        $tempBase = config('backup-complete-restore.temp_directory', storage_path('app/temp-restore'));
        $parent = dirname($tempBase);
        $prefixes = [basename($tempBase) . '-', 'temp-check-'];

        if (!File::exists($parent) || !File::isDirectory($parent)) {
            return [];
        }

        $cutoff = time() - ($olderThanMinutes * 60);
        $found = [];

        foreach (File::directories($parent) as $dir) {
            $base = basename($dir);
            foreach ($prefixes as $prefix) {
                if (str_starts_with($base, $prefix) && File::lastModified($dir) <= $cutoff) {
                    $found[] = $dir;
                    break;
                }
            }
        }

        sort($found);

        return $found;
    }

    private function directorySize(string $dir): int
    {
        $size = 0;
        try {
            foreach (File::allFiles($dir) as $file) {
                $size += $file->getSize();
            }
        } catch (Exception $e) {
            // Best effort only
        }

        return $size;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
