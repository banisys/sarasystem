<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields an organization may leave out of the «همکاری با ما» form become
     * nullable, and answers to the fields it added are kept with a copy of
     * each field (docs/decisions/0017-customizable-forms.md).
     */
    public function up(): void
    {
        Schema::table('employee_job_applications', function (Blueprint $table) {
            $table->string('father_name')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('postal_code', 10)->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->jsonb('custom_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_job_applications', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });
    }
};
