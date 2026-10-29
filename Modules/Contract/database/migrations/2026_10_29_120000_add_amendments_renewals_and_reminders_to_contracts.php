<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amendments and renewals of contracts, and what has been reminded
     * (docs/decisions/0021-contract-amendments-renewals-reminders.md).
     */
    public function up(): void
    {
        Schema::table('contract_types', function (Blueprint $table) {
            $table->boolean('is_amendment')->default(false)->after('parties');
        });

        Schema::table('contracts', function (Blueprint $table) {
            // An amendment's main contract, or the contract a renewal follows.
            $table->foreignId('parent_id')->nullable()->after('contract_type_id')->constrained('contracts')->nullOnDelete();
            $table->string('kind')->default('original')->after('parent_id');
            $table->timestamp('expiry_reminded_at')->nullable();
            $table->timestamp('signature_reminded_at')->nullable();
            $table->unsignedTinyInteger('signature_reminder_count')->default(0);

            $table->index(['status', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['status', 'ends_on']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['kind', 'expiry_reminded_at', 'signature_reminded_at', 'signature_reminder_count']);
        });

        Schema::table('contract_types', function (Blueprint $table) {
            $table->dropColumn('is_amendment');
        });
    }
};
