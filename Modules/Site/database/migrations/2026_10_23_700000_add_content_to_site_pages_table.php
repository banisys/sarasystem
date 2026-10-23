<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A page's content: its widgets in order, as [{type, data}, ...]
     * (docs/decisions/0011-site-widgets.md).
     */
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->jsonb('content')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
