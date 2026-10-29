<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terms and class roles (defined in the primary tenant), holidays,
     * classes and their members (docs/decisions/0022-classes.md).
     */
    public function up(): void
    {
        Schema::create('class_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The primary tenant's holidays are the whole organization's.
        Schema::create('class_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['tenant_id', 'starts_on']);
        });

        Schema::create('class_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('title');
            $table->boolean('is_attendee')->default(false);
            $table->boolean('takes_attendance')->default(false);
            $table->boolean('records_content')->default(false);
            // System roles a member must hold in the class's tenant; empty = anyone of the tenant.
            $table->jsonb('allowed_role_ids');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            // Terms are deactivated, not deleted, while classes use them.
            $table->foreignId('term_id')->nullable()->constrained('class_terms');
            $table->foreignId('parent_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->string('title');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->string('mode');
            $table->string('location')->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('class_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Roles are deleted only while nobody holds them.
            $table->foreignId('class_role_id')->constrained();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'user_id', 'class_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_members');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('class_roles');
        Schema::dropIfExists('class_holidays');
        Schema::dropIfExists('class_terms');
    }
};
