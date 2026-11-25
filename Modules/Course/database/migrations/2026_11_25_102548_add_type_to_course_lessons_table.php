<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->enum('type', ['file', 'video', 'exam'])->default('file');
        });

        DB::table('course_lessons')
            ->whereIn('id', DB::table('media')
                ->where('model_type', 'Modules\\Course\\Models\\CourseLesson')
                ->where('collection_name', 'video')
                ->select('model_id'))
            ->update(['type' => 'video']);
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
