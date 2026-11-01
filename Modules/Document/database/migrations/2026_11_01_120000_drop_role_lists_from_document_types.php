<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uploading and checking now follow the Document permissions and
     * workflow steps, not role lists on each type (ADR 0019).
     */
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->dropColumn(['upload_role_ids', 'verify_role_ids']);
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table): void {
            $table->jsonb('upload_role_ids')->default('[]');
            $table->jsonb('verify_role_ids')->default('[]');
        });
    }
};
