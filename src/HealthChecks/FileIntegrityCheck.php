<?php

namespace Klytron\LaravelBackupCompleteRestore\HealthChecks;

use Illuminate\Support\Facades\File;

class FileIntegrityCheck
{
    /**
     * Run the health check
     */
    public function run(): bool
    {
        $fileMappings = config('backup-complete-restore.file_mappings', []);
        
        foreach ($fileMappings as $backupPath => $localPath) {
            if (!File::exists($localPath)) {
                return false;
            }
            
            // Check if directory is readable
            if (!is_readable($localPath)) {
                return false;
            }
            
            // Check if directory has proper permissions
            $permissions = substr(sprintf('%o', fileperms($localPath)), -4);
            if ($permissions !== '0755' && $permissions !== '0775') {
                return false;
            }
        }
        
        return true;
    }
}
