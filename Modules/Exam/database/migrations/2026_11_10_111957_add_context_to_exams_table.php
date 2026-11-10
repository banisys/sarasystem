<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->string('context_type', 50)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->foreignId('template_exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->unique(['tenant_id', 'context_type', 'context_id', 'template_exam_id'], 'exams_context_template_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropUnique('exams_context_template_unique');
            $table->dropConstrainedForeignId('template_exam_id');
            $table->dropColumn(['context_type', 'context_id']);
        });
    }
};
