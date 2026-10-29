<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Score periods of each academic year (defined in the primary tenant)
     * and the scores teachers give in them
     * (docs/decisions/0032-scores-and-report-cards.md).
     */
    public function up(): void
    {
        Schema::create('school_score_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // Terms are deactivated, not deleted, while periods use them.
            $table->foreignId('term_id')->constrained('class_terms');
            $table->string('title');
            // What the period covers: absences and homework of these days.
            $table->date('starts_on');
            $table->date('ends_on');
            // Teachers enter scores until the end of this day.
            $table->date('entry_closes_on')->nullable();
            // Report cards are shown to learners and guardians.
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('school_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('period_id')->constrained('school_score_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('class_subjects');
            // Where it was given.
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            // A number out of 20, or a descriptive level, by the stage's scale.
            $table->decimal('score', 5, 2)->nullable();
            $table->string('level')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('entered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at');
            $table->timestamps();

            $table->unique(['period_id', 'user_id', 'subject_id']);
            $table->index(['tenant_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_scores');
        Schema::dropIfExists('school_score_periods');
    }
};
