<?php

namespace Klytron\LaravelBackupCompleteRestore\Tests\Commands;

use Illuminate\Support\Facades\File;
use Klytron\LaravelBackupCompleteRestore\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CleanupRestorationTempCommandTest extends TestCase
{
    #[Test]
    public function it_is_registered_as_an_artisan_command()
    {
        $this->artisan('list')
            ->expectsOutputToContain('klytron:backup:cleanup:restoration')
            ->assertExitCode(0);
    }

    #[Test]
    public function dry_run_lists_stale_dirs_without_deleting()
    {
        $stale = $this->makeStaleDir('temp-restore-test-dry-run');
        $staleCheck = $this->makeStaleDir('temp-check-test-dry-run');

        try {
            $this->artisan('klytron:backup:cleanup:restoration --dry-run --older-than=60')
                ->expectsOutput('🔍 Dry run — nothing deleted. Re-run without --dry-run to purge.')
                ->assertExitCode(0);

            $this->assertDirectoryExists($stale);
            $this->assertDirectoryExists($staleCheck);
        } finally {
            File::deleteDirectory($stale);
            File::deleteDirectory($staleCheck);
        }
    }

    #[Test]
    public function it_purges_stale_dirs_with_force()
    {
        $stale = $this->makeStaleDir('temp-restore-test-purge');
        $staleCheck = $this->makeStaleDir('temp-check-test-purge');

        $this->artisan('klytron:backup:cleanup:restoration --force --older-than=60')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($stale);
        $this->assertDirectoryDoesNotExist($staleCheck);
    }

    #[Test]
    public function it_keeps_fresh_dirs_outside_the_age_window()
    {
        $fresh = storage_path('app/temp-restore-test-fresh');
        File::makeDirectory($fresh, 0755, true);

        try {
            $this->artisan('klytron:backup:cleanup:restoration --force --older-than=60')
                ->expectsOutput('✅ No stale restoration temp directories found.')
                ->assertExitCode(0);

            $this->assertDirectoryExists($fresh);
        } finally {
            File::deleteDirectory($fresh);
        }
    }

    #[Test]
    public function it_ignores_unrelated_directories()
    {
        $other = storage_path('app/not-a-temp-dir');
        File::makeDirectory($other, 0755, true);
        touch($other, time() - 7200);

        try {
            $this->artisan('klytron:backup:cleanup:restoration --force --older-than=0')
                ->expectsOutput('✅ No stale restoration temp directories found.')
                ->assertExitCode(0);

            $this->assertDirectoryExists($other);
        } finally {
            File::deleteDirectory($other);
        }
    }

    private function makeStaleDir(string $name): string
    {
        $dir = storage_path('app/' . $name);
        File::makeDirectory($dir, 0755, true);
        File::put($dir . '/debug.txt', 'stale');
        touch($dir . '/debug.txt', time() - 7200);
        touch($dir, time() - 7200);

        return $dir;
    }
}
