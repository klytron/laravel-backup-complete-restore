<?php

namespace Klytron\LaravelBackupCompleteRestore\Commands;

/**
 * Backward Compatibility Layer
 * 
 * This file provides backward compatibility for the old class name.
 * It creates an alias from the old 'Scryba' prefixed class to the new 'Klytron' prefixed class.
 * 
 * @deprecated 1.5.0 Will be removed in 2.0.0. 
 * 
 * ### Migration Guide
 * 
 * Update your code to use the new class name:
 * 
 * ```php
 * // Old (deprecated)
 * use Klytron\LaravelBackupCompleteRestore\Commands\ScrybaBackupHealthCheckCommand;
 * 
 * // New
 * use Klytron\LaravelBackupCompleteRestore\Commands\KlytronBackupHealthCheckCommand;
 * ```
 * 
 * Or update your command references:
 * 
 * ```bash
 * # Old (deprecated)
 * php artisan scryba:backup-health-check
 * 
 * # New
 * php artisan klytron:backup-health-check
 * ```
 */

// Create an alias for backward compatibility
class_alias(
    \Klytron\LaravelBackupCompleteRestore\Commands\KlytronBackupHealthCheckCommand::class,
    __NAMESPACE__ . '\\ScrybaBackupHealthCheckCommand'
);