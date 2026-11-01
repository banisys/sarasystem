<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract type is for any party now (ADR 0020).
     */
    public function up(): void
    {
        Schema::table('contract_types', function (Blueprint $table): void {
            $table->dropColumn('parties');
        });
    }

    public function down(): void
    {
        Schema::table('contract_types', function (Blueprint $table): void {
            $table->jsonb('parties')->default('["employee","learner"]');
        });
    }
};
