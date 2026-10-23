<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menus of each tenant's site and their items as a tree
     * (docs/decisions/0012-site-menus.md).
     */
    public function up(): void
    {
        Schema::create('site_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('site_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('menu_id')->constrained('site_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('site_menu_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('link_type');
            $table->foreignId('page_id')->nullable()->constrained('site_pages')->nullOnDelete();
            $table->string('url')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_menu_items');
        Schema::dropIfExists('site_menus');
    }
};
