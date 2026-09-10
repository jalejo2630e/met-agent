<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notas internas de una conversación de la bandeja (por número). Solo las ve
     * el operador; NO se envían al cliente. Sirven de contexto durante el chat.
     */
    public function up(): void
    {
        Schema::create('conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('from_number');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['agent_id', 'from_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_notes');
    }
};
