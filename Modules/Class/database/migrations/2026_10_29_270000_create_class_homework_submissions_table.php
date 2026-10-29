<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Learners' homework handed in, with the teacher's score and feedback,
     * and the score a session's homework is out of
     * (docs/decisions/0030-homework-submissions.md).
     */
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('homework_max_score')->nullable()->after('homework_due_on');
        });

        Schema::create('class_homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamp('submitted_at');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->index(['classroom_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_homework_submissions');

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn('homework_max_score');
        });
    }
};
