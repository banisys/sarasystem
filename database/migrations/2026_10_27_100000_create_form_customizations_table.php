<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How each tenant set up a customizable form, for the whole form (no
     * owner) or for one record, e.g. a job position
     * (docs/decisions/0017-customizable-forms.md).
     */
    public function up(): void
    {
        Schema::create('form_customizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('form');
            $table->nullableMorphs('owner');
            // Built-in field key => mode (required / optional / hidden).
            $table->jsonb('field_modes');
            // Added fields, in form order.
            $table->jsonb('fields');
            $table->timestamps();
        });

        // One row per form and owner (or per form without owner) in a tenant.
        DB::statement('CREATE UNIQUE INDEX form_customizations_unique ON form_customizations (tenant_id, form, owner_type, owner_id) NULLS NOT DISTINCT');
    }

    public function down(): void
    {
        Schema::dropIfExists('form_customizations');
    }
};
