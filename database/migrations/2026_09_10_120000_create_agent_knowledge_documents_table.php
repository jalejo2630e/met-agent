<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base de conocimiento por agente: documentos (PDF convertido a Markdown,
     * o texto/markdown cargado directamente) que se inyectan como contexto en
     * el prompt del agente conversacional.
     */
    public function up(): void
    {
        Schema::create('agent_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->longText('content')->nullable(); // Markdown resultante
            $table->boolean('enabled')->default(true); // si se inyecta en el prompt
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_knowledge_documents');
    }
};
