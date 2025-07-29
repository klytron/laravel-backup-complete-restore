<?php

namespace Scryba\LaravelBackupCompleteRestore\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Scryba\LaravelBackupCompleteRestore\BackupCompleteRestoreServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            BackupCompleteRestoreServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }
}
