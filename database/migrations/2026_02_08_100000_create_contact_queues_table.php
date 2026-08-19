<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // 'call' | 'whatsapp'
            $table->json('client_ids'); // [1, 2, 3, ...]
            $table->string('status', 20)->default('pending'); // pending, processing, completed, cancelled
            $table->json('schedule_snapshot')->nullable(); // { times: ['09:00','11:00'], days_of_week: [1,2,3,4,5], excluded_dates: [], timezone: 'America/Bogota' }
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_queues');
    }
};
