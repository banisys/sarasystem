<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable();
            $table->string('alias')->nullable();
            $table->text('short_description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('locale', 35)->nullable();
            $table->string('translation_key')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->unique(['tenant_id', 'alias']);
            $table->index(['tenant_id', 'parent_id']);
            $table->foreign(['tenant_id', 'parent_id'])->references(['tenant_id', 'id'])->on('course_categories');
        });
    }

    public function down(): void
    {
        Schema::table('course_categories', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'parent_id']);
            $table->dropIndex(['tenant_id', 'parent_id']);
            $table->dropUnique(['tenant_id', 'alias']);
            $table->dropColumn([
                'parent_id', 'alias', 'short_description', 'position', 'is_active', 'locale', 'translation_key', 'metadata',
            ]);
        });
    }
};
