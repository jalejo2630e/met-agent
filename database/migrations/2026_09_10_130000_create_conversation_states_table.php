<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de la conversación de la bandeja (por número): permite pausar el bot
     * para que un humano tome el control, aunque el número no tenga cliente.
     */
    public function up(): void
    {
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('from_number');
            $table->boolean('bot_paused')->default(false);
            $table->timestamps();

            $table->unique(['agent_id', 'from_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
