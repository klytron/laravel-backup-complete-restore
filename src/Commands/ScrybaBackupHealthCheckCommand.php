<?php

namespace Klytron\LaravelBackupCompleteRestore\Commands;

// Backward-compatible alias for old class/file name
class_alias(
    \Klytron\LaravelBackupCompleteRestore\Commands\KlytronBackupHealthCheckCommand::class,
    __NAMESPACE__ . '\\ScrybaBackupHealthCheckCommand'
);