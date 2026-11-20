<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['class_schedules', 'class_sessions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('mode')->nullable();
            });

            DB::table($tableName)->update([
                'mode' => DB::table('classrooms')->whereColumn('classrooms.id', $tableName.'.classroom_id')->select('mode'),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['class_schedules', 'class_sessions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('mode');
            });
        }
    }
};
