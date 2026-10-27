<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document types (defined in the primary tenant), where they are
     * required, and the documents of applicants and members
     * (docs/decisions/0019-documents.md).
     */
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            // Holder keys (App\Documents\DocumentHolder), e.g. ["employee", "learner"].
            $table->jsonb('holders');
            $table->string('file_kind');
            $table->unsignedTinyInteger('max_files')->default(1);
            $table->unsignedInteger('max_kilobytes')->default(5120);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('applicant_uploads')->default(false);
            $table->jsonb('upload_role_ids');
            $table->jsonb('verify_role_ids');
            $table->string('required_for');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Requirement owners (e.g. job positions) a type is required for,
        // when it is required for some of them only.
        Schema::create('document_type_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('holder');
            $table->unsignedBigInteger('owner_id');

            $table->unique(['document_type_id', 'holder', 'owner_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // Types are deactivated, not deleted, while documents use them.
            $table->foreignId('document_type_id')->constrained();
            $table->string('holder');
            $table->morphs('documentable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->text('rejection_reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_type_requirements');
        Schema::dropIfExists('document_types');
    }
};
