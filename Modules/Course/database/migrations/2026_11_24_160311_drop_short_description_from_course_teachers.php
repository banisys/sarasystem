<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The teacher profile keeps only the full biography.
     */
    public function up(): void
    {
        Schema::table('course_teachers', function (Blueprint $table): void {
            $table->dropColumn('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('course_teachers', function (Blueprint $table): void {
            $table->text('short_description')->nullable();
        });
    }
};
