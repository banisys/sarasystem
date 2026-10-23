<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every tenant has a home page ("/") in its page list (ADR 0010); new
     * tenants get one when they are created, existing ones here.
     */
    public function up(): void
    {
        $now = now();

        DB::table('tenants')
            ->whereNotExists(fn ($query) => $query->from('site_pages')->whereColumn('site_pages.tenant_id', 'tenants.id')->where('path', '/'))
            ->get(['id', 'name'])
            ->each(fn (object $tenant) => DB::table('site_pages')->insert([
                'tenant_id' => $tenant->id,
                'title' => $tenant->name,
                'path' => '/',
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        // The home pages stay; they are ordinary rows from here on.
    }
};
