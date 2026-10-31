<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One step-by-step tutorial per person at a time (ADR 0040).
        Schema::create('tutorial_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('scenario', 50);
            // Mission key => when it was done.
            $table->jsonb('completed_missions')->default('{}');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Every row made while the run's tutorial mode was on, so that
        // starting again deletes them.
        Schema::create('tutorial_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('tutorial_runs')->cascadeOnDelete();
            $table->string('model_type');
            $table->string('model_id', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_records');
        Schema::dropIfExists('tutorial_runs');
    }
};
