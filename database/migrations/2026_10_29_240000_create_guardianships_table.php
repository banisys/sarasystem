<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who is whose parent or guardian, between users and for every tenant
     * (docs/decisions/0027-guardians.md).
     */
    public function up(): void
    {
        Schema::create('guardianships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship');
            // Gets the SMS sent about the ward (e.g. absences, later).
            $table->boolean('receives_sms')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['guardian_id', 'ward_id']);
            $table->index('ward_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardianships');
    }
};
