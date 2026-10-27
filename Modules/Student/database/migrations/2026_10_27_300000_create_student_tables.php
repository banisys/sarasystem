<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programs offered in the public preregistration form, the preregistrations
     * sent through it and the learners created when one is admitted at the
     * last step of its workflow (docs/decisions/0018-student-preregistration.md).
     */
    public function up(): void
    {
        Schema::create('student_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('student_preregistrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('program_id')->nullable()->constrained('student_programs')->nullOnDelete();
            $table->string('program_title');
            $table->foreignId('preferred_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('status');
            $table->timestamp('closed_at')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('father_name')->nullable();
            $table->string('nationality');
            $table->string('national_code', 12);
            $table->date('birth_date')->nullable();
            $table->string('gender');
            $table->string('mobile', 11)->index();
            $table->string('phone', 11)->nullable();
            $table->string('email')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->jsonb('custom_fields')->nullable();
            $table->timestamps();

            $table->index('national_code');
        });

        // One open preregistration per national code, across all tenants.
        DB::statement('CREATE UNIQUE INDEX student_preregistrations_open_national_code_unique ON student_preregistrations (national_code) WHERE closed_at IS NULL');

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('preregistration_id')->nullable()->constrained('student_preregistrations')->nullOnDelete();
            $table->string('student_code', 20)->nullable()->unique();
            $table->string('status');
            $table->date('enrolled_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('student_preregistrations');
        Schema::dropIfExists('student_programs');
    }
};
