<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['exam_question_banks', 'exam_questions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_shared')->default(false);
                $table->index(['tenant_id', 'owner_id']);
            });
        }

        Schema::table('exams', function (Blueprint $table): void {
            $table->jsonb('question_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('question_snapshot'));

        foreach (['exam_question_banks', 'exam_questions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['tenant_id', 'owner_id']);
                $table->dropConstrainedForeignId('owner_id');
                $table->dropColumn('is_shared');
            });
        }
    }
};
