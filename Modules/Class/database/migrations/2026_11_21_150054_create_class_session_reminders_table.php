<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_session_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignId('source_session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['tenant_id', 'session_id']);
            $table->index('source_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_session_reminders');
    }
};
