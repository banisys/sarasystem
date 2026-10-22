<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Global role (tenant_id NULL) marking staff who may use the admin panel
        // in the tenants where they hold it.
        Role::findOrCreate(Utils::getPanelUserRoleName(), 'web');

        Tenant::firstOrCreate(['slug' => 'head-office'], ['name' => 'ستاد']);

        if (app()->isLocal()) {
            User::firstOrCreate(
                ['email' => 'admin@sarasystem.test'],
                ['name' => 'Super Admin', 'password' => 'password', 'is_super_admin' => true],
            );
        }
    }
}
