<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The super admin becomes the global super_admin role instead of the
     * users.is_super_admin flag (docs/decisions/0007-super-admin-role.md).
     * Flagged users get the role in the head office, or in the oldest tenant
     * when there is no head office.
     */
    public function up(): void
    {
        $roleId = $this->superAdminRoleId();

        $superAdminIds = DB::table('users')->where('is_super_admin', true)->pluck('id');

        if ($superAdminIds->isNotEmpty()) {
            $tenantId = DB::table('tenants')->where('slug', 'head-office')->value('id')
                ?? DB::table('tenants')->orderBy('id')->value('id')
                ?? DB::table('tenants')->insertGetId(['name' => 'ستاد', 'slug' => 'head-office', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

            DB::table(config('permission.table_names.model_has_roles'))->insertOrIgnore($superAdminIds->map(fn (int $userId): array => [
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                config('permission.column_names.model_morph_key') => $userId,
                'tenant_id' => $tenantId,
            ])->all());
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        $roleId = $this->superAdminRoleId();
        $modelHasRoles = config('permission.table_names.model_has_roles');

        DB::table('users')
            ->whereIn('id', DB::table($modelHasRoles)->where('role_id', $roleId)->where('model_type', 'App\\Models\\User')->select(config('permission.column_names.model_morph_key')))
            ->update(['is_super_admin' => true]);

        DB::table($modelHasRoles)->where('role_id', $roleId)->delete();
        DB::table(config('permission.table_names.roles'))->where('id', $roleId)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdminRoleId(): int
    {
        $roles = config('permission.table_names.roles');

        $id = DB::table($roles)->whereNull('tenant_id')->where('name', 'super_admin')->where('guard_name', 'web')->value('id');

        return $id ?? DB::table($roles)->insertGetId([
            'name' => 'super_admin',
            'label' => 'مدیر کل سیستم',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
