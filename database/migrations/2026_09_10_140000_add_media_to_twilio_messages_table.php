<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda la media (URL pública y tipo) de los mensajes salientes con archivo
     * para poder previsualizarla dentro del hilo de la bandeja.
     */
    public function up(): void
    {
        Schema::table('twilio_messages', function (Blueprint $table) {
            $table->string('media_url')->nullable()->after('body');
            $table->string('media_type')->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('twilio_messages', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type']);
        });
    }
};
