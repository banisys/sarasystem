<?php

namespace Modules\Contract\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run with `php artisan module:seed Contract` (module seeders are not part
 * of `db:seed`), after the Employee and Student module seeders.
 */
class ContractDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call(ContractDemoSeeder::class);
        }
    }
}
