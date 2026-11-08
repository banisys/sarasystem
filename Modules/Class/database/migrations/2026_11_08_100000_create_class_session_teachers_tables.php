<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several teachers per schedule and per session (ADR 0068): the single
 * teacher_id / substitute_teacher_id columns become rows, each session
 * teacher with an optional substitute who teaches instead of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedule_teachers', function (Blueprint $table) {
            $table->foreignId('schedule_id')->constrained('class_schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['schedule_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('class_session_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('substitute_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['session_id', 'teacher_id']);
            $table->index('teacher_id');
            $table->index('substitute_id');
        });

        DB::statement('insert into class_schedule_teachers (schedule_id, user_id) select id, teacher_id from class_schedules where teacher_id is not null');

        // A substitute on a session without a teacher becomes its teacher.
        DB::statement('insert into class_session_teachers (session_id, teacher_id, substitute_id)
            select id, coalesce(teacher_id, substitute_teacher_id), case when teacher_id is null then null else substitute_teacher_id end
            from class_sessions where coalesce(teacher_id, substitute_teacher_id) is not null');

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex(['teacher_id', 'starts_at']);
            $table->dropConstrainedForeignId('substitute_teacher_id');
            $table->dropConstrainedForeignId('teacher_id');
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('substitute_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['teacher_id', 'starts_at']);
        });

        // Only the first teacher of each survives going back.
        DB::statement('update class_schedules set teacher_id = (select min(user_id) from class_schedule_teachers where schedule_id = class_schedules.id)');
        DB::statement('update class_sessions set
            teacher_id = (select teacher_id from class_session_teachers where session_id = class_sessions.id order by id limit 1),
            substitute_teacher_id = (select substitute_id from class_session_teachers where session_id = class_sessions.id order by id limit 1)');

        Schema::dropIfExists('class_session_teachers');
        Schema::dropIfExists('class_schedule_teachers');
    }
};
