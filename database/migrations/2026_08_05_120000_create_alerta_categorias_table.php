<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 120)->unique();
            $table->string('color', 7)->nullable()->comment('Color hex para la UI, p. ej. #ef4444');
            $table->timestamps();
        });

        // Categorías base. El CRUD queda abierto para agregar más desde configuración.
        $now = now();
        DB::table('alerta_categorias')->insert([
            ['nombre' => 'Problema', 'slug' => 'problema', 'color' => '#ef4444', 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Alerta', 'slug' => 'alerta', 'color' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_categorias');
    }
};
