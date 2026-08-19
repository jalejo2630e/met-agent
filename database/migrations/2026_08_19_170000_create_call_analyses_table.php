<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id');
            $table->string('phone', 50)->nullable();
            $table->text('summary')->nullable();
            $table->string('sentiment', 20)->nullable();
            $table->string('category_slug', 120)->nullable();
            $table->string('motivo')->nullable();
            $table->string('resultado', 30)->nullable();
            $table->boolean('requires_alert')->default(false);
            $table->foreignId('alerta_llamada_id')->nullable()->constrained('alertas_llamada')->nullOnDelete();
            $table->string('model')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_analyses');
    }
};
