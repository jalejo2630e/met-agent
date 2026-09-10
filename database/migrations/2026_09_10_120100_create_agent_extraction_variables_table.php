<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variables de recolección que la IA debe EXTRAER de la conversación
     * (WhatsApp/SMS): nombre de la variable, qué extraer (descripción) y tipo.
     */
    public function up(): void
    {
        Schema::create('agent_extraction_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // clave (snake_case)
            $table->string('label')->nullable(); // etiqueta legible opcional
            $table->text('description'); // qué debe extraer la IA
            $table->string('type')->default('string'); // string, number, boolean, json
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_extraction_variables');
    }
};
