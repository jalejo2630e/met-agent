<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valores extraídos por la IA de cada conversación (agrupados por número).
     * Se actualiza a medida que la conversación avanza.
     */
    public function up(): void
    {
        Schema::create('conversation_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('from_number');
            $table->json('values')->nullable(); // { variable_name: valor }
            $table->timestamps();

            $table->unique(['agent_id', 'from_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_extractions');
    }
};
