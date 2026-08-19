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
        Schema::create('n8n_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('api_url');
            $table->text('api_key');
            $table->string('webhook_base_url')->nullable();
            $table->string('default_workflow_template_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('n8n_configurations');
    }
};
