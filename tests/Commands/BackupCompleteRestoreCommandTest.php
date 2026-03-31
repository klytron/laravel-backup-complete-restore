<?php

namespace Klytron\LaravelBackupCompleteRestore\Tests\Commands;

use Klytron\LaravelBackupCompleteRestore\Tests\TestCase;
use Klytron\LaravelBackupCompleteRestore\Commands\BackupCompleteRestoreCommand;
use ReflectionClass;

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

    /** @test */
    public function it_parses_sql_with_semicolons_in_string_literals()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        $sql = "INSERT INTO users (name) VALUES ('value;with;semicolons');";
        $statements = $method->invoke($command, $sql);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString("'value;with;semicolons'", $statements[0]);
    }

    /** @test */
    public function it_parses_sql_with_escaped_quotes()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        // SQL standard escaping with doubled quotes
        $sql = "INSERT INTO users (name) VALUES ('it''s a test');";
        $statements = $method->invoke($command, $sql);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString("'it''s a test'", $statements[0]);
    }

    /** @test */
    public function it_parses_sql_with_backslash_escaped_quotes()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        // MySQL-style backslash escaping
        $sql = "INSERT INTO users (name) VALUES ('it\\'s a test');";
        $statements = $method->invoke($command, $sql);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString("'it\\'s a test'", $statements[0]);
    }

    /** @test */
    public function it_parses_sql_with_double_quotes_in_strings()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        $sql = 'INSERT INTO users (name) VALUES ("value;with;semicolons");';
        $statements = $method->invoke($command, $sql);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('"value;with;semicolons"', $statements[0]);
    }

    /** @test */
    public function it_parses_multiple_sql_statements()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        $sql = "CREATE TABLE users (id INT); INSERT INTO users VALUES (1); SELECT * FROM users;";
        $statements = $method->invoke($command, $sql);

        $this->assertCount(3, $statements);
        $this->assertStringContainsString('CREATE TABLE', $statements[0]);
        $this->assertStringContainsString('INSERT INTO', $statements[1]);
        $this->assertStringContainsString('SELECT *', $statements[2]);
    }

    /** @test */
    public function it_handles_sql_comments()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        $sql = "-- This is a comment\nINSERT INTO users VALUES (1); /* multi-line\ncomment */ SELECT * FROM users;";
        $statements = $method->invoke($command, $sql);

        $this->assertCount(2, $statements);
    }

    /** @test */
    public function it_handles_complex_real_world_sql_with_special_chars()
    {
        $command = new BackupCompleteRestoreCommand();
        $method = (new ReflectionClass($command))->getMethod('parseSqlStatements');
        $method->setAccessible(true);

        // Complex SQL with multiple special characters
        $sql = "INSERT INTO posts (title, content) VALUES 
            ('Title with; semicolon', 'Content with ''quotes'' and // slashes and ; semicolons');
            INSERT INTO comments (text) VALUES ('Another; test');";

        $statements = $method->invoke($command, $sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'Title with; semicolon'", $statements[0]);
        $this->assertStringContainsString("'Another; test'", $statements[1]);
    }
}
