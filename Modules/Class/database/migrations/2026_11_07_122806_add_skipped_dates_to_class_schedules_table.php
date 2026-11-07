<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The days whose session was deleted by hand, so that rebuilding the
     * schedule doesn't bring them back
     * (docs/decisions/0066-deleting-unheld-sessions.md).
     */
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->jsonb('skipped_dates')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropColumn('skipped_dates');
        });
    }
};
