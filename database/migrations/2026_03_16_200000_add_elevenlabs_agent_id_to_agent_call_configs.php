<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_call_configs', function (Blueprint $table) {
            $table->string('elevenlabs_agent_id')->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('agent_call_configs', function (Blueprint $table) {
            $table->dropColumn('elevenlabs_agent_id');
        });
    }
};
