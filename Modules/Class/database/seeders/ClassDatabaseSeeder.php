<?php

namespace Modules\Class\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run with `php artisan module:seed Class` (module seeders are not part
 * of `db:seed`), after the core seeder.
 */
class ClassDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call(ClassDemoSeeder::class);
        }
    }
}
