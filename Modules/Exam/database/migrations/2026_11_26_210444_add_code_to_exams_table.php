<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->string('code', 64)->nullable();
        });

        DB::table('exams')->update(['code' => DB::raw("concat('EXM-', id)")]);

        Schema::table('exams', function (Blueprint $table): void {
            $table->string('code', 64)->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropColumn('code');
        });
    }
};
