<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_programs', function (Blueprint $table): void {
            $table->boolean('all_tenants')->default(true);
        });

        Schema::create('student_program_tenant', function (Blueprint $table): void {
            $table->foreignId('program_id')->constrained('student_programs')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->primary(['program_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_program_tenant');
        Schema::table('student_programs', function (Blueprint $table): void {
            $table->dropColumn('all_tenants');
        });
    }
};
