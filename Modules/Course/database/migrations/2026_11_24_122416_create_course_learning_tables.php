<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
            $table->unique(['tenant_id', 'title']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_category_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('allows_self_enrollment')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'is_published', 'id']);
            $table->index(['tenant_id', 'course_category_id']);
            $table->foreign(['tenant_id', 'course_category_id'])->references(['tenant_id', 'id'])->on('course_categories');
        });

        Schema::create('course_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id');
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'course_id', 'id']);
            $table->foreign(['tenant_id', 'course_id'])->references(['tenant_id', 'id'])->on('courses')->cascadeOnDelete();
        });

        Schema::create('course_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id');
            $table->foreignId('course_section_id');
            $table->string('title');
            $table->text('body')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'course_id', 'course_section_id']);
            $table->foreign(['tenant_id', 'course_id', 'course_section_id'], 'course_lessons_section_fk')
                ->references(['tenant_id', 'course_id', 'id'])->on('course_sections');
        });

        Schema::create('course_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'course_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->foreign(['tenant_id', 'course_id'])->references(['tenant_id', 'id'])->on('courses')->cascadeOnDelete();
        });

        Schema::create('course_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'course_lesson_id', 'user_id']);
            $table->foreign(['tenant_id', 'course_lesson_id'])->references(['tenant_id', 'id'])->on('course_lessons')->cascadeOnDelete();
        });

        Schema::create('course_instructors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'course_id', 'user_id']);
            $table->foreign(['tenant_id', 'course_id'])->references(['tenant_id', 'id'])->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_instructors');
        Schema::dropIfExists('course_completions');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_categories');
    }
};
