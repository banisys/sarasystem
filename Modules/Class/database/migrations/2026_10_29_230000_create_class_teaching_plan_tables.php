<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teaching plans (defined in the primary tenant) and their topics, the
     * plan a schedule follows, and what each session covered
     * (docs/decisions/0025-teaching-plans-and-session-content.md).
     */
    public function up(): void
    {
        Schema::create('class_teaching_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // Subjects are deactivated, not deleted, while plans use them.
            $table->foreignId('subject_id')->nullable()->constrained('class_subjects');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('class_plan_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_plan_id')->constrained('class_teaching_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            // How many sessions it takes.
            $table->unsignedTinyInteger('sessions')->default(1);
            $table->timestamps();
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            // Plans are deactivated, not deleted, while schedules use them.
            $table->foreignId('teaching_plan_id')->nullable()->after('teacher_id')->constrained('class_teaching_plans');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->jsonb('covered_topic_ids')->nullable();
            $table->text('summary')->nullable();
            $table->text('homework')->nullable();
            $table->date('homework_due_on')->nullable();
            $table->foreignId('content_recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('content_recorded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_recorded_by_id');
            $table->dropColumn(['covered_topic_ids', 'summary', 'homework', 'homework_due_on', 'content_recorded_at']);
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teaching_plan_id');
        });

        Schema::dropIfExists('class_plan_topics');
        Schema::dropIfExists('class_teaching_plans');
    }
};
