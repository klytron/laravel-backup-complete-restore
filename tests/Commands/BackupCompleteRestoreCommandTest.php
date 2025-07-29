<?php

namespace Scryba\LaravelBackupCompleteRestore\Tests\Commands;

use Scryba\LaravelBackupCompleteRestore\Tests\TestCase;
use Scryba\LaravelBackupCompleteRestore\Commands\BackupCompleteRestoreCommand;

class BackupCompleteRestoreCommandTest extends TestCase
{
    /** @test */
    public function it_can_list_available_commands()
    {
        $this->artisan('list')
            ->expectsOutput('backup:restore-complete')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_help_information()
    {
        $this->artisan('backup:restore-complete --help')
            ->expectsOutput('Complete restore of database AND files from Spatie Laravel Backup')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_list_backups_when_none_exist()
    {
        $this->artisan('backup:restore-complete --list')
            ->expectsOutput('📋 Available Backups')
            ->expectsOutput('No backups found')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_requires_confirmation_without_force_flag()
    {
        $this->artisan('backup:restore-complete')
            ->expectsQuestion('Are you sure you want to continue?', false)
            ->expectsOutput('❌ Restore operation cancelled.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_can_run_with_force_flag()
    {
        $this->artisan('backup:restore-complete --force')
            ->expectsOutput('❌ Backup file not found!')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_validates_configuration_exists()
    {
        $this->assertTrue(config()->has('backup-complete-restore'));
        $this->assertIsArray(config('backup-complete-restore.file_mappings'));
        $this->assertIsString(config('backup-complete-restore.container_base_path'));
    }
}
