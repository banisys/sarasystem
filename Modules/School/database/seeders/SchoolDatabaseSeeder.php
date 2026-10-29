<?php

namespace Modules\School\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run with `php artisan module:seed School` (module seeders are not part
 * of `db:seed`), after the Class and Student module seeders.
 */
class SchoolDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call(SchoolDemoSeeder::class);
        }
    }
}
