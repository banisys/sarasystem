<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract types (defined in the primary tenant) and the contracts of
     * applicants and members (docs/decisions/0020-contracts.md).
     */
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            // Party keys (App\Contract\ContractParty), e.g. ["employee"].
            $table->jsonb('parties');
            // Rich editor HTML with merge tags.
            $table->longText('body')->nullable();
            $table->string('signature_method');
            $table->string('number_prefix', 20)->nullable();
            // The last number given to a contract of this type.
            $table->unsignedInteger('last_number')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // Types are deactivated, not deleted, while contracts use them.
            $table->foreignId('contract_type_id')->constrained();
            $table->string('party');
            $table->morphs('contractable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->nullable();
            $table->string('status');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            // Answers to the type's added fields (ADR 0017).
            $table->jsonb('answers')->nullable();
            // The text as issued, and its SHA-256.
            $table->longText('body')->nullable();
            $table->string('body_hash', 64)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_via')->nullable();
            $table->string('signed_mobile', 20)->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->foreignId('signature_recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['contract_type_id', 'number']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_types');
    }
};
