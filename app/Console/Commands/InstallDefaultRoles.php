<?php

namespace App\Console\Commands;

use App\Authorization\DefaultRoles;
use Illuminate\Console\Command;

/**
 * Run by DatabaseSeeder; safe to run again (ADR 0039).
 */
class InstallDefaultRoles extends Command
{
    protected $signature = 'roles:install-defaults {--add-missing-permissions : Give existing default roles the default permissions they lack; none is taken away}';

    protected $description = 'Make the missing default roles with their default permissions';

    public function handle(DefaultRoles $defaultRoles): int
    {
        $made = $defaultRoles->install((bool) $this->option('add-missing-permissions'));

        $this->info($made === [] ? 'All default roles exist.' : 'Made: '.implode(', ', $made).'.');

        return self::SUCCESS;
    }
}
