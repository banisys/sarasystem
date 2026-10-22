<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users log in with a mobile, national code or email (ADR 0004).
     * Mobile is not unique: siblings often share a parent's mobile.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('mobile', 11)->nullable()->index()->after('email');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            $table->string('national_code', 10)->nullable()->unique()->after('mobile_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mobile', 'mobile_verified_at', 'national_code']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
