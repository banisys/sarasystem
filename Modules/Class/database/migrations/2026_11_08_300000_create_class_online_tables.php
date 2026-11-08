<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online classes on several platforms (docs/decisions/0073-online-class-platforms.md):
     * the installation's servers (Adobe Connect, …), a class's rooms, each
     * in effect from a day, and the platform accounts made for people. The
     * class's link moves into a «لینک» room from the beginning.
     */
    public function up(): void
    {
        Schema::create('online_servers', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);
            $table->string('title');
            $table->string('host', 500);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('online_server_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('online_servers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->timestamps();
            $table->unique(['server_id', 'user_id']);
        });

        Schema::create('class_online_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('platform', 30);
            $table->foreignId('server_id')->nullable()->constrained('online_servers')->restrictOnDelete();
            $table->date('starts_on')->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->string('external_id')->nullable();
            $table->string('url_path', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['classroom_id', 'starts_on']);
        });

        DB::table('classrooms')->whereNotNull('meeting_url')->orderBy('id')->each(function (object $classroom): void {
            DB::table('class_online_rooms')->insert([
                'tenant_id' => $classroom->tenant_id,
                'classroom_id' => $classroom->id,
                'platform' => 'link',
                'meeting_url' => $classroom->meeting_url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('meeting_url');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('meeting_url', 500)->nullable()->after('location');
        });

        DB::table('class_online_rooms')->where('platform', 'link')->whereNotNull('meeting_url')
            ->orderBy('starts_on')->orderBy('id')
            ->each(fn (object $room) => DB::table('classrooms')->where('id', $room->classroom_id)->update(['meeting_url' => $room->meeting_url]));

        Schema::dropIfExists('class_online_rooms');
        Schema::dropIfExists('online_server_accounts');
        Schema::dropIfExists('online_servers');
    }
};
