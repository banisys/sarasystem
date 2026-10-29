<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subjects (defined in the primary tenant), class schedules and the
     * sessions built from them (docs/decisions/0023-class-schedules-and-sessions.md).
     */
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            // Subjects are deactivated, not deleted, while schedules use them.
            $table->foreignId('subject_id')->nullable()->constrained('class_subjects');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('frequency');
            $table->unsignedSmallInteger('interval')->default(1);
            // Carbon day-of-week numbers (0 = Sunday … 6 = Saturday).
            $table->jsonb('weekdays');
            // Monthly: a Jalali day of the month, or the nth (1–4, -1 = last) weekday.
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->smallInteger('month_week')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            // Past and changed sessions outlive their schedule.
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('class_subjects');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('substitute_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('location')->nullable();
            $table->string('kind');
            $table->string('status');
            $table->string('cancel_reason')->nullable();
            // Changed by hand: its schedule no longer rebuilds it.
            $table->boolean('is_modified')->default(false);
            $table->timestamp('original_starts_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['classroom_id', 'starts_at']);
            $table->index(['tenant_id', 'starts_at']);
            $table->index(['teacher_id', 'starts_at']);
            $table->index(['schedule_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('class_subjects');
    }
};
