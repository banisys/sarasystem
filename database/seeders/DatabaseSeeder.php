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
        $panelUserRole = Role::findOrCreate(Utils::getPanelUserRoleName(), 'web');
        $panelUserRole->fill(['label' => 'کارمند (ورود به پنل)'])->save();

        $headOffice = Tenant::firstOrCreate(['slug' => 'head-office'], ['name' => 'ستاد']);

        if (app()->isLocal()) {
            $superAdmin = User::firstOrCreate(
                ['email' => 'admin@sarasystem.test'],
                ['name' => 'Super Admin', 'password' => 'password', 'is_super_admin' => true],
            );

            // Membership in a tenant means holding a role there, so the super
            // admin gets panel_user in the head office to show up in its users list.
            $headOffice->execute(fn () => $superAdmin->assignRole($panelUserRole));
        }
    }
}
