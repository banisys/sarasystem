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

        // Global role whose holders are installation-wide super admins (ADR 0007).
        Role::findOrCreate(Role::SUPER_ADMIN, 'web')->fill(['label' => 'مدیر کل سیستم'])->save();

        $headOffice = Tenant::firstOrCreate(['slug' => 'head-office'], ['name' => 'ستاد']);

        if (app()->isLocal()) {
            $superAdmin = User::firstOrCreate(
                ['email' => 'admin@sarasystem.test'],
                ['name' => 'Super Admin', 'password' => 'password'],
            );

            $headOffice->execute(fn () => $superAdmin->assignRole([Role::SUPER_ADMIN, $panelUserRole]));

            $this->call(DemoSeeder::class);
        }
    }
}
