<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job positions people can apply for, and the applications sent through
     * the public «همکاری با ما» form (docs/decisions/0014-job-applications.md).
     */
    public function up(): void
    {
        Schema::create('employee_job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('job_position_id')->nullable()->constrained('employee_job_positions')->nullOnDelete();
            $table->string('job_title');
            $table->string('status');
            $table->timestamp('closed_at')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('father_name');
            $table->string('nationality');
            $table->string('national_code', 12);
            $table->date('birth_date');
            $table->string('gender');
            $table->string('military_status')->nullable();
            $table->string('mobile', 11)->index();
            $table->string('phone', 11)->nullable();
            $table->string('email');
            $table->string('postal_code', 10);
            $table->text('address');
            $table->jsonb('education');
            $table->jsonb('work_history');
            $table->timestamps();

            $table->index('national_code');
        });

        // One open application per national code, across all tenants.
        DB::statement('CREATE UNIQUE INDEX employee_job_applications_open_national_code_unique ON employee_job_applications (national_code) WHERE closed_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_job_applications');
        Schema::dropIfExists('employee_job_positions');
    }
};
