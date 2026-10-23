<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online exams: question banks, questions, exams drawing questions from
     * banks, participants, attempts and their answers
     * (docs/decisions/0013-exams.md).
     */
    public function up(): void
    {
        Schema::create('exam_question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('type');
            $table->text('body');
            $table->jsonb('options')->nullable();
            $table->decimal('score', 6, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_bank_question', function (Blueprint $table) {
            $table->foreignId('question_bank_id')->constrained('exam_question_banks')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();

            $table->primary(['question_bank_id', 'question_id']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('grace_minutes')->default(3);
            $table->decimal('total_score', 6, 2)->default(20);
            $table->decimal('passing_score', 6, 2)->default(12);
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->string('retake_policy')->default('always');
            $table->unsignedSmallInteger('questions_per_page')->default(5);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->unsignedSmallInteger('negative_marking')->nullable();
            $table->string('result_visibility')->default('hidden');
            $table->timestamps();
        });

        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained('exam_question_banks')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_count')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('exam_participant_users', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['exam_id', 'user_id']);
        });

        Schema::create('exam_participant_roles', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            $table->primary(['exam_id', 'role_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at');
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('timed_out')->default(false);
            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('max_raw_score', 8, 2)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'user_id']);
        });

        Schema::create('exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('exam_questions')->nullOnDelete();
            $table->unsignedSmallInteger('sort');
            $table->string('question_type');
            $table->text('question_body');
            $table->jsonb('options')->nullable();
            $table->string('correct_option')->nullable();
            $table->decimal('max_score', 6, 2);
            $table->string('selected_option')->nullable();
            $table->text('text_answer')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_participant_roles');
        Schema::dropIfExists('exam_participant_users');
        Schema::dropIfExists('exam_sections');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_bank_question');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exam_question_banks');
    }
};
