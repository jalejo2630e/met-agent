<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_audio_llamadas', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('created_at')->useCurrent();
            $table->text('conversation_id')->nullable();
            $table->text('audio')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_audio_llamadas');
    }
};
