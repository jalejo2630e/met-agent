<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_llamada', function (Blueprint $table) {
            $table->id();
            // Un cliente puede tener múltiples alertas. Se guarda igual aunque el
            // teléfono no coincida con un cliente (client_id null).
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alerta_categoria_id')->nullable()->constrained('alerta_categorias')->nullOnDelete();
            $table->string('phone', 50)->nullable();
            $table->unsignedInteger('numero_llamada')->nullable();
            $table->unsignedInteger('paso_llamada')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_llamada');
    }
};
