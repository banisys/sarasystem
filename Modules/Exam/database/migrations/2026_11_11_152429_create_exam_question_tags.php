<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_question_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->timestamps();

            $table->unique(['tenant_id', 'title']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('exam_question_question_tag', function (Blueprint $table): void {
            $table->foreignId('tenant_id');
            $table->foreignId('question_id');
            $table->foreignId('question_tag_id');

            $table->primary(['question_id', 'question_tag_id']);
            $table->index('question_tag_id');
            $table->foreign(['tenant_id', 'question_id'], 'exam_question_tag_question_fk')
                ->references(['tenant_id', 'id'])->on('exam_questions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'question_tag_id'], 'exam_question_tag_tag_fk')
                ->references(['tenant_id', 'id'])->on('exam_question_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_question_question_tag');
        Schema::dropIfExists('exam_question_tags');

        Schema::table('exam_questions', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'id']);
        });
    }
};
