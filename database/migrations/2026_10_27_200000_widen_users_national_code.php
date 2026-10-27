<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign nationals are identified by a 12-digit code (کد اتباع); hired
     * applicants (ADR 0014) and admitted students (ADR 0018) keep theirs.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_code', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_code', 10)->nullable()->change();
        });
    }
};
