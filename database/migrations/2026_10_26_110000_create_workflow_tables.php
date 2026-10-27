<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approval workflows (فرایند), their running cases and each case's
     * history (docs/decisions/0016-workflows.md).
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('subject_type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('steps');
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type']);
        });

        Schema::create('workflow_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->string('workflow_name');
            $table->morphs('subject');
            // Copy of the workflow's steps when the case started.
            $table->jsonb('steps');
            $table->string('status');
            $table->unsignedSmallInteger('current_step')->nullable();
            // The current step's assignee, copied from `steps` for the cartable query.
            $table->foreignId('assignee_tenant_id')->nullable()->constrained('tenants');
            $table->foreignId('assignee_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('claimed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('step_started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'assignee_tenant_id']);
        });

        Schema::create('workflow_case_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('from_step')->nullable();
            $table->unsignedSmallInteger('to_step')->nullable();
            $table->text('comment')->nullable();
            $table->jsonb('changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_case_actions');
        Schema::dropIfExists('workflow_cases');
        Schema::dropIfExists('workflows');
    }
};
