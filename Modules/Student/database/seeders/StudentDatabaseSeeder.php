<?php

namespace Modules\Student\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run with `php artisan module:seed Student` (module seeders are not part
 * of `db:seed`).
 */
class StudentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call([AdmissionDemoSeeder::class, GuardianDemoSeeder::class]);
        }
    }
}
