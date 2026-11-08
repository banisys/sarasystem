<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who last moved, cancelled, restored the session or changed its
     * teachers, and when; only the last one (docs/decisions/0072-last-change-on-session-page.md).
     */
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->foreignId('changed_by_id')->nullable()->after('note')->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable()->after('changed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('changed_by_id');
            $table->dropColumn('changed_at');
        });
    }
};
