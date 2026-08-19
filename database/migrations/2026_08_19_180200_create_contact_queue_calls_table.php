<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_queue_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 50)->nullable();
            // pending | in_flight | done | failed | timed_out
            $table->string('status', 20)->default('pending');
            $table->string('conversation_id')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['contact_queue_id', 'status']);
            $table->index(['agent_id', 'status']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_queue_calls');
    }
};
