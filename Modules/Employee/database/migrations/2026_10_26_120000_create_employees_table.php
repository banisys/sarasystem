<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hiring workflow per job position, and the employees created when an
     * application is approved at its last step (docs/decisions/0016-workflows.md).
     */
    public function up(): void
    {
        Schema::table('employee_job_positions', function (Blueprint $table) {
            $table->foreignId('workflow_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('job_application_id')->nullable()->constrained('employee_job_applications')->nullOnDelete();
            $table->string('job_title');
            $table->string('personnel_code', 20)->nullable()->unique();
            $table->string('employment_type');
            $table->string('status');
            $table->date('hired_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');

        Schema::table('employee_job_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
        });
    }
};
