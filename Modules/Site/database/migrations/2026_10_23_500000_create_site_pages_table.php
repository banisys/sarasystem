<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public pages of each tenant's site (docs/decisions/0010-site-pages.md).
     * The content (widgets) comes in a later step.
     */
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->string('path');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
