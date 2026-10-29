<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school's structure (docs/decisions/0031-school-structure.md): stages,
     * grades and majors with each grade's curriculum (defined in the primary
     * tenant), classes' grades, and each learner's grade per academic year.
     */
    public function up(): void
    {
        Schema::create('school_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            // How its scores are given: numeric (0–20) or descriptive.
            $table->string('scale');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('school_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // Stages are deleted only while they have no grades.
            $table->foreignId('stage_id')->constrained('school_stages');
            $table->string('title');
            $table->boolean('has_majors')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('school_majors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // A grade's subjects (for one major, or every major when null).
        Schema::create('school_curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('school_grades')->cascadeOnDelete();
            $table->foreignId('major_id')->nullable()->constrained('school_majors')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('class_subjects');
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->unsignedTinyInteger('weekly_hours')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['grade_id', 'major_id', 'subject_id']);
        });

        Schema::create('school_classroom_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('classroom_id')->unique()->constrained()->cascadeOnDelete();
            // Grades are deleted only while nothing uses them.
            $table->foreignId('grade_id')->constrained('school_grades');
            $table->foreignId('major_id')->nullable()->constrained('school_majors');
            $table->timestamps();
        });

        // A learner's grade (and main class) in an academic year.
        Schema::create('school_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('class_terms');
            $table->foreignId('grade_id')->constrained('school_grades');
            $table->foreignId('major_id')->nullable()->constrained('school_majors');
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_enrollments');
        Schema::dropIfExists('school_classroom_grades');
        Schema::dropIfExists('school_curricula');
        Schema::dropIfExists('school_majors');
        Schema::dropIfExists('school_grades');
        Schema::dropIfExists('school_stages');
    }
};
