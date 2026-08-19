<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_message_configs', function (Blueprint $table) {
            $table->json('plantillas')->nullable()->after('schedule_config')
                ->comment('Lista de plantillas: [{ id, name }]');
            $table->string('default_plantilla_id', 255)->nullable()->after('plantillas')
                ->comment('ID de plantilla principal para contacto manual');
        });
    }

    public function down(): void
    {
        Schema::table('agent_message_configs', function (Blueprint $table) {
            $table->dropColumn(['plantillas', 'default_plantilla_id']);
        });
    }
};
