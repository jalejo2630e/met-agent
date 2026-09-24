<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca de archivos: el archivo siempre se guarda en el disco privado.
     * Si is_public = true, se sirve sin sesión en /f/{token}; si no, solo a
     * usuarios autenticados. Cambiar la visibilidad solo cambia este flag.
     */
    public function up(): void
    {
        Schema::create('library_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('original_filename');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_public')->default(false);
            $table->string('token', 64)->unique(); // enlace público no adivinable
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_files');
    }
};
