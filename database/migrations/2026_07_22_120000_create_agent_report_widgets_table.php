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
        Schema::create('agent_report_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Métrica: range_buckets | client_progress | value_counts | calls
            $table->string('metric');
            // Origen del dato: custom_field | calls
            $table->string('source')->default('custom_field');
            // Campo dinámico del cliente a leer (custom_fields), null para calls
            $table->string('field_name')->nullable();
            // Gráfica: doughnut | bar | funnel | stat
            $table->string('chart_type')->default('doughnut');
            // Reglas del widget (tramos, valores, max, métricas de llamadas...)
            $table->json('config')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_report_widgets');
    }
};
