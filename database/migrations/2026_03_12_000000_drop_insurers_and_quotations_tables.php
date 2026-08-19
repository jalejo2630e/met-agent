<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina todas las tablas relacionadas con aseguradoras y cotizaciones.
     */
    public function up(): void
    {
        Schema::dropIfExists('client_quotations');
        Schema::dropIfExists('agent_quotation_templates');
        Schema::dropIfExists('insurer_plan_data_medical_directory_list');
        Schema::dropIfExists('insurer_medical_directory_entries');
        Schema::dropIfExists('insurer_medical_directory_lists');
        Schema::dropIfExists('insurer_plan_data');
        Schema::dropIfExists('insurers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte: las tablas se recrearían con las migraciones originales si se hace rollback de esta.
    }
};