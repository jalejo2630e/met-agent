<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log crudo del webhook Post-Call de ElevenLabs: guarda TODO lo recibido en cada
 * evento (payload completo SIN el audio, que ya vive en registro_audio_llamadas
 * y se enlaza por conversation_id) para depuración e inspección.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_call_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversation_id')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('event_type')->nullable();      // payload.type (p.ej. post_call_transcription)
            $table->string('status', 50)->nullable();       // data.status
            $table->string('call_successful', 30)->nullable(); // analysis.call_successful
            $table->unsignedInteger('duration_secs')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->boolean('has_audio')->default(false);
            $table->json('payload')->nullable();            // payload completo sin full_audio
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_call_webhook_logs');
    }
};
