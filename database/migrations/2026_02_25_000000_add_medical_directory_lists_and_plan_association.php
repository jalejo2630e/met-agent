<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear tabla de listados de directorio médico (pertenecen a aseguradora)
        Schema::create('insurer_medical_directory_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // 2. Agregar list_id a entradas (nullable primero)
        Schema::table('insurer_medical_directory_entries', function (Blueprint $table) {
            $table->foreignId('list_id')->nullable()->after('id')->constrained('insurer_medical_directory_lists')->cascadeOnDelete();
        });

        // 3. Crear listado por defecto por aseguradora y asignar entradas existentes
        $insurers = DB::table('insurers')->pluck('id');
        foreach ($insurers as $insurerId) {
            $listId = DB::table('insurer_medical_directory_lists')->insertGetId([
                'insurer_id' => $insurerId,
                'name' => 'Directorio general',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('insurer_medical_directory_entries')
                ->where('insurer_id', $insurerId)
                ->update(['list_id' => $listId]);
        }

        // 4. Hacer list_id obligatorio y eliminar insurer_id
        Schema::table('insurer_medical_directory_entries', function (Blueprint $table) {
            $table->foreignId('list_id')->nullable(false)->change();
            $table->dropForeign(['insurer_id']);
            $table->dropColumn('insurer_id');
        });

        // 4. Pivot: un listado puede servir a varios planes, un plan puede tener varios listados
        Schema::create('insurer_plan_data_medical_directory_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurer_plan_data_id')->constrained('insurer_plan_data')->cascadeOnDelete();
            $table->foreignId('insurer_medical_directory_list_id')->constrained('insurer_medical_directory_lists')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['insurer_plan_data_id', 'insurer_medical_directory_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurer_plan_data_medical_directory_list');

        Schema::table('insurer_medical_directory_entries', function (Blueprint $table) {
            $table->foreignId('insurer_id')->nullable()->after('id');
        });

        $lists = DB::table('insurer_medical_directory_lists')->get();
        foreach ($lists as $list) {
            DB::table('insurer_medical_directory_entries')
                ->where('list_id', $list->id)
                ->update(['insurer_id' => $list->insurer_id]);
        }

        Schema::table('insurer_medical_directory_entries', function (Blueprint $table) {
            $table->dropForeign(['list_id']);
            $table->dropColumn('list_id');
            $table->foreignId('insurer_id')->nullable(false)->change();
            $table->foreign('insurer_id')->references('id')->on('insurers')->cascadeOnDelete();
        });

        Schema::dropIfExists('insurer_medical_directory_lists');
    }
};
