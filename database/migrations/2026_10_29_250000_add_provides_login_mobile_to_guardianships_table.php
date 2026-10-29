<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ward signs in with this guardian's mobile, kept in step with it
     * (docs/decisions/0028-guardian-details-and-login-mobile.md).
     */
    public function up(): void
    {
        Schema::table('guardianships', function (Blueprint $table) {
            $table->boolean('provides_login_mobile')->default(false)->after('receives_sms');
        });
    }

    public function down(): void
    {
        Schema::table('guardianships', function (Blueprint $table) {
            $table->dropColumn('provides_login_mobile');
        });
    }
};
