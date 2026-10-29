<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The parents' details, the learner's own mobile and whose mobile the
     * learner signs in with, when the organization's form asks them
     * (docs/decisions/0028-guardian-details-and-login-mobile.md).
     */
    public function up(): void
    {
        Schema::table('student_preregistrations', function (Blueprint $table) {
            // {name, national_code, mobile}
            $table->jsonb('father')->nullable();
            $table->jsonb('mother')->nullable();
            $table->string('learner_mobile', 11)->nullable();
            $table->string('login_mobile_from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_preregistrations', function (Blueprint $table) {
            $table->dropColumn(['father', 'mother', 'learner_mobile', 'login_mobile_from']);
        });
    }
};
