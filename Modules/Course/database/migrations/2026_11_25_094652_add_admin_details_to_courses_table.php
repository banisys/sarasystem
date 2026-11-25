<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('code', 64)->nullable();
            $table->string('type')->default('normal');
            $table->unsignedInteger('capacity')->nullable();
            $table->text('description_html')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('video_cover')->nullable();
            $table->string('intro_video')->nullable();
            $table->jsonb('gallery_images')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('prepayment_amount')->nullable();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('course_category_course', function (Blueprint $table): void {
            $table->foreignId('tenant_id');
            $table->foreignId('course_id');
            $table->foreignId('course_category_id');
            $table->primary(['tenant_id', 'course_id', 'course_category_id']);
            $table->index(['tenant_id', 'course_category_id']);
            $table->foreign(['tenant_id', 'course_id'])->references(['tenant_id', 'id'])->on('courses')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'course_category_id'], 'course_additional_category_fk')
                ->references(['tenant_id', 'id'])->on('course_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_category_course');

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropColumn([
                'code', 'type', 'capacity', 'description_html', 'cover_image', 'video_cover',
                'intro_video', 'gallery_images', 'starts_on', 'ends_on', 'prepayment_amount',
            ]);
        });
    }
};
