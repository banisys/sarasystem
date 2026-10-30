<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retain former owner IDs as upgrade history; definitions no longer use them.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->change();
        });
    }

    /**
     * Global definitions have no original owner to restore. Keep the history
     * and nullable column intact; use a forward migration if reverting the model.
     */
    public function down(): void {}
};
