<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A platform's room per session instead of one for the whole class
     * (docs/decisions/0074-online-room-per-session.md): the room says which,
     * and each session's meeting on the platform is kept here.
     */
    public function up(): void
    {
        Schema::table('class_online_rooms', function (Blueprint $table) {
            $table->boolean('per_session')->default(false)->after('server_id');
        });

        Schema::create('class_online_session_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('class_online_rooms')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('url_path', 500)->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_online_session_meetings');

        Schema::table('class_online_rooms', function (Blueprint $table) {
            $table->dropColumn('per_session');
        });
    }
};
