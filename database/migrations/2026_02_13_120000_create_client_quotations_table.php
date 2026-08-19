<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_key')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('period')->nullable();
            $table->json('quotation_data')->nullable()->comment('detalle, total, periodo_seleccionado, etc.');
            $table->string('pdf_path')->nullable()->comment('Ruta en storage: Supabase o local');
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_quotations');
    }
};
