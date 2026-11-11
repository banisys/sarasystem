<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_question_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('parent_id')->nullable();
            $table->string('title');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'parent_id']);
            $table->foreign(['tenant_id', 'parent_id'])
                ->references(['tenant_id', 'id'])->on('exam_question_categories')->restrictOnDelete();
        });

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable();
            $table->index(['tenant_id', 'category_id']);
            $table->foreign(['tenant_id', 'category_id'])
                ->references(['tenant_id', 'id'])->on('exam_question_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'category_id']);
            $table->dropIndex(['tenant_id', 'category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('exam_question_categories');
    }
};
