<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_endpoint_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('test'); // test, integration
            $table->string('request_method')->default('GET');
            $table->text('request_url')->nullable();
            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_endpoint_logs');
    }
};
