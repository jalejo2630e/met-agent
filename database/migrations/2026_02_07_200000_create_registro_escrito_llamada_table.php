<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_escrito_llamada', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversation_id')->nullable();
            $table->string('status')->nullable();
            $table->text('transcript')->nullable();
            $table->string('phone')->nullable();
            $table->text('summary')->nullable();
            $table->decimal('costo', 12, 2)->nullable();
            $table->unsignedSmallInteger('duration_call_seg')->nullable();
            $table->text('variables_extraidas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_escrito_llamada');
    }
};
