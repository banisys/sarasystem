<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the guardians were told about this absence, so it is sent once
     * (docs/decisions/0029-absence-sms.md).
     */
    public function up(): void
    {
        Schema::table('class_attendances', function (Blueprint $table) {
            $table->timestamp('absence_notified_at')->nullable()->after('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('class_attendances', function (Blueprint $table) {
            $table->dropColumn('absence_notified_at');
        });
    }
};
