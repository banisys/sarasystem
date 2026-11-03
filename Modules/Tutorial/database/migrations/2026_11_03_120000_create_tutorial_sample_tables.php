<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One «ساخت داده‌ی نمونه» (docs/decisions/0046-sample-class-data.md):
        // a class with everything around it, deleted again in one go.
        Schema::create('tutorial_sample_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The form's values, to show what was asked for.
            $table->jsonb('options')->default('{}');
            $table->timestamps();
        });

        // Every row the batch made, so deleting the batch deletes them.
        Schema::create('tutorial_sample_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('tutorial_sample_batches')->cascadeOnDelete();
            $table->string('model_type');
            $table->string('model_id', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_sample_records');
        Schema::dropIfExists('tutorial_sample_batches');
    }
};
