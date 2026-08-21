<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pausa de IA por cliente: mientras está en true, el agente de IA nativo (webhook
 * de Twilio) NO responde automáticamente a ese cliente (toma de control humano).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('ai_paused')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('ai_paused');
        });
    }
};
