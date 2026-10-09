<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('twilio_messages', function (Blueprint $table) {
            // Sede (bogota | chia) según el número de WhatsApp que recibió el mensaje.
            $table->string('sede', 20)->nullable()->after('channel');
            $table->string('to_number', 50)->nullable()->after('from_number');
            $table->index(['agent_id', 'sede', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('twilio_messages', function (Blueprint $table) {
            $table->dropIndex(['agent_id', 'sede', 'created_at']);
            $table->dropColumn(['sede', 'to_number']);
        });
    }
};
