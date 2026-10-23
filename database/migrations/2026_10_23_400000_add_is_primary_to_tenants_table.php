<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The primary tenant's home page is the site root (/); see
     * docs/decisions/0009-tenant-home-pages.md. At most one tenant is primary.
     * The oldest tenant (the head office in a seeded installation) starts as it.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('is_active');
        });

        DB::statement('create unique index tenants_single_primary on tenants (is_primary) where is_primary');

        DB::table('tenants')->where('id', DB::table('tenants')->min('id'))->update(['is_primary' => true]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('tenants_single_primary');
            $table->dropColumn('is_primary');
        });
    }
};
