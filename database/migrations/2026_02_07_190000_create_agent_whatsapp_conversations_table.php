<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 255);
            $table->jsonb('message');
            $table->timestamps();
        });

        Schema::table('agent_whatsapp_conversations', function (Blueprint $table) {
            $table->index(['agent_id', 'session_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_whatsapp_conversations');
    }
};
