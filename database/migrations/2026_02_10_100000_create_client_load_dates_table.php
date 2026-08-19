<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_load_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->timestamp('loaded_at');
            $table->string('source', 50)->nullable()->comment('import, manual, api');
            $table->timestamps();
        });

        Schema::table('client_load_dates', function (Blueprint $table) {
            $table->index(['agent_id', 'loaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_load_dates');
    }
};
