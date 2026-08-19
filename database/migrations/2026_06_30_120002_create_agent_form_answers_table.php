<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_form_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_form_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_question_id')->nullable()->constrained()->nullOnDelete();
            // Copia del texto de la pregunta al momento de responder (para conservar
            // el reporte legible aunque luego se edite o elimine la pregunta).
            $table->text('question_label');
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_form_answers');
    }
};
