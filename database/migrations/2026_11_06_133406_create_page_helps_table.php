<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Texts of the panel's page help as the organization edited them (ADR 0065).
     * A page without a row shows the default text in its code.
     */
    public function up(): void
    {
        Schema::create('page_helps', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_helps');
    }
};
