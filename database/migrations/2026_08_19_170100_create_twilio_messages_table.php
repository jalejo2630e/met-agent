<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('from_number', 50);
            $table->string('direction', 10); // inbound | outbound
            $table->text('body')->nullable();
            $table->string('message_sid')->nullable();
            $table->string('profile_name')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'from_number', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_messages');
    }
};
