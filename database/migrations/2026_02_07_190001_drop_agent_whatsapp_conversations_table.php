<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_whatsapp_conversations');
    }

    public function down(): void
    {
        Schema::create('agent_whatsapp_conversations', function ($table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 255);
            $table->json('message');
            $table->timestamps();
        });
    }
};
