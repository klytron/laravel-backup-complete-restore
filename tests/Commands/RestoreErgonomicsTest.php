<?php

namespace Klytron\LaravelBackupCompleteRestore\Tests\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Klytron\LaravelBackupCompleteRestore\Commands\BackupCompleteRestoreCommand;
use Klytron\LaravelBackupCompleteRestore\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use ZipArchive;

class RestoreErgonomicsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('backup.backup.name', 'RestoreErgonomicsApp');
        config()->set('backup-complete-restore.backup_existing_files', false);
    }

    private function makeCommand(): BackupCompleteRestoreCommand
    {
        $command = new BackupCompleteRestoreCommand();
        $command->setLaravel($this->app);
        $input = new ArrayInput([], $command->getDefinition());
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $ref = new \ReflectionClass($command);
        $inputProp = $ref->getProperty('input');
        $inputProp->setAccessible(true);
        $inputProp->setValue($command, $input);
        $outputProp = $ref->getProperty('output');
        $outputProp->setAccessible(true);
        $outputProp->setValue($command, new \Illuminate\Console\OutputStyle($input, $output));

        return $command;
    }

    private function makeBackupZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-test-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function putBackupOnDisk(string $filename, string $zipPath): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(
            'RestoreErgonomicsApp/' . $filename,
            file_get_contents($zipPath)
        );
    }

    #[Test]
    public function it_exposes_keep_temp_dry_run_and_verification_flags()
    {
        $command = new BackupCompleteRestoreCommand();
        $definition = $command->getDefinition();

        foreach (['keep-temp', 'dry-run', 'skip-verification', 'boot-probe'] as $flag) {
            $this->assertTrue($definition->hasOption($flag), "Missing --{$flag} option");
        }
    }

    #[Test]
    public function dry_run_lists_the_restore_plan_and_restores_nothing()
    {
        $zipPath = $this->makeBackupZip([
            'db-dumps/sqlite-dry-run.sql' => 'CREATE TABLE dry_run_probe (id INTEGER);',
            'var/www/html/storage/app/public/test.txt' => 'hello',
            'var/www/html/public/test.txt' => 'hello',
        ]);
        $this->putBackupOnDisk('dry-run.zip', $zipPath);

        $this->artisan('backup:restore-complete --backup=dry-run.zip --dry-run')
            ->expectsOutput('🔍 Dry run — nothing will be restored')
            ->expectsOutput('📋 Restore plan:')
            ->assertExitCode(0);

        $this->assertFalse(Schema::connection('testing')->hasTable('dry_run_probe'));
        $this->assertFileDoesNotExist(storage_path('app/public/test.txt'));

        @unlink($zipPath);
    }

    #[Test]
    public function dry_run_reports_archive_stats_and_targets()
    {
        $zipPath = $this->makeBackupZip([
            'db-dumps/sqlite-stats.sql' => 'CREATE TABLE stats_probe (id INTEGER);',
        ]);
        $this->putBackupOnDisk('stats.zip', $zipPath);

        $this->artisan('backup:restore-complete --backup=stats.zip --dry-run --connection=testing')
            ->expectsOutput('📦 Archive: stats.zip')
            ->expectsOutput("   • Database → connection 'testing' (1 dump(s))")
            ->assertExitCode(0);

        @unlink($zipPath);
    }

    #[Test]
    public function keep_temp_preserves_the_extraction_directory()
    {
        $zipPath = $this->makeBackupZip([
            'db-dumps/sqlite-keep-temp.sql' => 'CREATE TABLE keep_temp_probe (id INTEGER); INSERT INTO keep_temp_probe VALUES (1);',
        ]);
        $this->putBackupOnDisk('keep-temp.zip', $zipPath);

        $before = glob(storage_path('app/temp-restore-*') ?: []) ?: [];

        $this->artisan('backup:restore-complete --backup=keep-temp.zip --force --keep-temp --database-only')
            ->assertExitCode(0);

        $this->assertTrue(Schema::connection('testing')->hasTable('keep_temp_probe'));

        $after = glob(storage_path('app/temp-restore-*') ?: []) ?: [];
        $kept = array_diff($after, $before);
        $this->assertNotEmpty($kept, 'Expected a kept temp-restore-* directory');

        foreach ($kept as $dir) {
            File::deleteDirectory($dir);
        }
        Schema::connection('testing')->dropIfExists('keep_temp_probe');
        @unlink($zipPath);
    }

    #[Test]
    public function temp_directory_is_removed_by_default()
    {
        $zipPath = $this->makeBackupZip([
            'db-dumps/sqlite-default-cleanup.sql' => 'CREATE TABLE default_cleanup_probe (id INTEGER);',
        ]);
        $this->putBackupOnDisk('default-cleanup.zip', $zipPath);

        $before = glob(storage_path('app/temp-restore-*') ?: []) ?: [];

        $this->artisan('backup:restore-complete --backup=default-cleanup.zip --force --database-only')
            ->assertExitCode(0);

        $after = glob(storage_path('app/temp-restore-*') ?: []) ?: [];
        $this->assertSame($before, $after, 'No new temp-restore-* directories should remain');

        Schema::connection('testing')->dropIfExists('default_cleanup_probe');
        @unlink($zipPath);
    }

    #[Test]
    public function restore_streams_extraction_progress()
    {
        $zipPath = $this->makeBackupZip([
            'db-dumps/sqlite-progress.sql' => 'CREATE TABLE progress_probe (id INTEGER);',
            'var/www/html/storage/app/public/a.txt' => 'a',
            'var/www/html/storage/app/public/b.txt' => 'b',
        ]);
        $this->putBackupOnDisk('progress.zip', $zipPath);

        $this->artisan('backup:restore-complete --backup=progress.zip --force --database-only')
            ->expectsOutput('📦 Extracting 3 files...')
            ->assertExitCode(0);

        Schema::connection('testing')->dropIfExists('progress_probe');
        @unlink($zipPath);
    }

    #[Test]
    public function sqlite_restore_prints_a_verification_summary()
    {
        $zipPath = $this->makeBackupZip([
            'db-dumps/sqlite-verify.sql' => 'CREATE TABLE verify_probe (id INTEGER);',
        ]);
        $this->putBackupOnDisk('verify.zip', $zipPath);

        $this->artisan('backup:restore-complete --backup=verify.zip --force --database-only')
            ->expectsOutput('✅ SQLite integrity_check: ok')
            ->assertExitCode(0);

        Schema::connection('testing')->dropIfExists('verify_probe');
        @unlink($zipPath);
    }

    #[Test]
    public function sqlite_verification_supports_a_boot_probe()
    {
        $command = $this->makeCommand();
        $method = new \ReflectionMethod($command, 'verifySqliteRestore');
        $method->setAccessible(true);

        config()->set('backup-complete-restore.restoration.sqlite_boot_probe', true);

        $this->assertTrue($method->invoke($command, 'testing'));
    }

    #[Test]
    public function verification_is_a_noop_for_non_sqlite_drivers()
    {
        $command = $this->makeCommand();
        $method = new \ReflectionMethod($command, 'verifySqliteRestore');
        $method->setAccessible(true);

        config()->set('database.connections.fake-mysql', ['driver' => 'mysql']);

        $this->assertTrue($method->invoke($command, 'fake-mysql'));
    }
}
