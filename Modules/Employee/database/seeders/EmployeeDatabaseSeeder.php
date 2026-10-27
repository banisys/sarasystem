<?php

namespace Modules\Employee\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run with `php artisan module:seed Employee` (module seeders are not part
 * of `db:seed`).
 */
class EmployeeDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call(HiringDemoSeeder::class);
        }
    }
}
