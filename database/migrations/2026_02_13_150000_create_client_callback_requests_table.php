<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_callback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_date')->comment('Fecha en que se debe ejecutar la llamada/mensaje');
            $table->string('scheduled_time', 5)->nullable()->comment('Hora opcional (HH:mm)');
            $table->string('channel', 20)->default('call')->comment('call o whatsapp');
            $table->text('notes')->nullable()->comment('Notas de la solicitud');
            $table->string('status', 20)->default('pending')->comment('pending, completed, cancelled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'scheduled_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_callback_requests');
    }
};
