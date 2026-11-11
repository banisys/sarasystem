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
            $table->jsonb('accepted_answers')->nullable();
        });

        Schema::table('exam_attempt_answers', function (Blueprint $table): void {
            $table->jsonb('accepted_answers')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempt_answers', function (Blueprint $table): void {
            $table->dropColumn('accepted_answers');
        });

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropColumn('accepted_answers');
        });
    }
};
