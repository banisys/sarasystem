<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['classrooms', 'class_schedules', 'class_sessions'] as $table) {
            DB::table($table)->where('mode', 'blended')->update(['mode' => 'online']);
        }
    }

    /**
     * Previously blended records cannot be distinguished from online records.
     */
    public function down(): void {}
};
