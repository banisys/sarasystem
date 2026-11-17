<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sections', function (Blueprint $table): void {
            $table->jsonb('question_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table): void {
            $table->dropColumn('question_ids');
        });
    }
};
