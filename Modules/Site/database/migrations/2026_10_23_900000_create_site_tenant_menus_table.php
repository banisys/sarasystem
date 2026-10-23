<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The menu shown in each tenant's site header, chosen in the tenants
     * list; it may be a menu made in another tenant, e.g. the head office
     * (docs/decisions/0012-site-menus.md).
     */
    public function up(): void
    {
        Schema::create('site_tenant_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('site_menus')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_tenant_menus');
    }
};
