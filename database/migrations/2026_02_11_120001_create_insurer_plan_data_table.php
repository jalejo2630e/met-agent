<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurer_plan_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurer_id')->constrained()->cascadeOnDelete();
            $table->string('plan_key')->comment('ej: fesalud_original_hombres, plan_dental_vip');
            $table->string('plan_name')->nullable();
            $table->string('gender')->nullable()->comment('Hombres, Mujeres o null');
            $table->string('data_type')->default('tarifas_edad')->comment('tarifas_edad, tarifas_grupo, anexo');
            $table->json('data')->comment('JSON completo del archivo');
            $table->timestamps();

            $table->unique(['insurer_id', 'plan_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurer_plan_data');
    }
};
