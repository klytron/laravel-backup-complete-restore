<?php

namespace Klytron\LaravelBackupCompleteRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class KlytronBackupHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klytron:backup:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform comprehensive health checks after backup restoration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏥 Performing Klytron Backup Health Checks...');
        
        $checks = [
            'Database Connection' => [$this, 'checkDatabaseConnection'],
            'Storage Directories' => [$this, 'checkStorageDirectories'],
            'Critical Files' => [$this, 'checkCriticalFiles'],
            'Database Tables' => [$this, 'checkDatabaseTables'],
            'Application Configuration' => [$this, 'checkApplicationConfiguration'],
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($checks as $checkName => $checkMethod) {
            $this->line("Checking: $checkName");
            
            try {
                $result = $checkMethod();
                if ($result) {
                    $this->info("✅ $checkName: PASSED");
                    $passed++;
                } else {
                    $this->error("❌ $checkName: FAILED");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("❌ $checkName: FAILED - " . $e->getMessage());
                $failed++;
            }
        }
        
        $this->newLine();
        $this->info("📊 Health Check Summary:");
        $this->info("Passed: $passed");
        $this->info("Failed: $failed");
        $this->info("Total: " . count($checks));
        
        if ($failed > 0) {
            $this->warn("⚠️  Some health checks failed. Please review the issues above.");
            return 1;
        } else {
            $this->info("🎉 All health checks passed! Backup restoration appears successful.");
            return 0;
        }
    }
    
    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check storage directories
     */
    private function checkStorageDirectories(): bool
    {
        $requiredDirs = [
            storage_path('app'),
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check critical files
     */
    private function checkCriticalFiles(): bool
    {
        $criticalFiles = [
            base_path('.env'),
            base_path('config/app.php'),
            base_path('config/database.php'),
        ];
        
        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check database tables
     */
    private function checkDatabaseTables(): bool
    {
        try {
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver", 'mysql');

            if (class_exists(Schema::class) && method_exists(Schema::class, 'getTables')) {
                $tables = Schema::getTables();
                $tableCount = count($tables);
            } else {
                switch ($driver) {
                    case 'sqlite':
                        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                        break;
                    case 'pgsql':
                        $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog', 'information_schema')");
                        break;
                    case 'sqlsrv':
                        $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                        break;
                    case 'mysql':
                    case 'mariadb':
                    default:
                        $tables = DB::select('SHOW TABLES');
                        break;
                }
                $tableCount = count($tables);
            }
            
            if ($tableCount === 0) {
                $this->warn("⚠️  No database tables found");
                return false;
            }
            
            $this->info("Found $tableCount database tables");
            return true;
        } catch (\Exception $e) {
            $this->warn("⚠️  Failed to check database tables: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check application configuration
     */
    private function checkApplicationConfiguration(): bool
    {
        // Standard Laravel config keys with environment variable fallbacks
        $requiredConfigs = [
            'app.name' => 'APP_NAME',
            'app.env' => 'APP_ENV',
            'app.key' => 'APP_KEY',
        ];
        
        $missing = [];
        
        foreach ($requiredConfigs as $configKey => $envFallback) {
            $value = config($configKey) ?? config($envFallback) ?? env($envFallback);
            if ($value === null || $value === '') {
                $missing[] = $configKey;
            }
        }

        // Check app.debug (boolean, can be false but shouldn't be null)
        if (config('app.debug') === null && config('APP_DEBUG') === null && env('APP_DEBUG') === null) {
            $missing[] = 'app.debug';
        }
        
        if (!empty($missing)) {
            $this->warn("Configuration missing: " . implode(', ', $missing));
            return false;
        }
        
        return true;
    }
}
