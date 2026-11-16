<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->timestamps();

            $table->unique(['tenant_id', 'title']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->foreignId('exam_group_id')->nullable();
            $table->index(['tenant_id', 'exam_group_id']);
            $table->foreign(['tenant_id', 'exam_group_id'])
                ->references(['tenant_id', 'id'])->on('exam_groups')
                ->onDelete('set null (exam_group_id)');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'exam_group_id']);
            $table->dropIndex(['tenant_id', 'exam_group_id']);
            $table->dropColumn('exam_group_id');
        });

        Schema::dropIfExists('exam_groups');
    }
};
