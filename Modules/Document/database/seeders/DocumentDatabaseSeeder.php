<?php

namespace Modules\Document\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Run with `php artisan module:seed Document` (module seeders are not part
 * of `db:seed`), after the Employee and Student module seeders.
 */
class DocumentDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call(DocumentDemoSeeder::class);
        }
    }
}
