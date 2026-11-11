<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->text('model_answer')->nullable();
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table): void {
            $table->text('model_answer')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempt_answers', function (Blueprint $table): void {
            $table->dropColumn('model_answer');
        });

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropColumn('model_answer');
        });
    }
};
