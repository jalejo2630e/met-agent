<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('registro_escrito_llamada')) {
            return;
        }
        if (! Schema::hasColumn('registro_escrito_llamada', 'campana_id')) {
            Schema::table('registro_escrito_llamada', function (Blueprint $table) {
                $table->uuid('campana_id')->nullable()->after('agent_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('registro_escrito_llamada') && Schema::hasColumn('registro_escrito_llamada', 'campana_id')) {
            Schema::table('registro_escrito_llamada', function (Blueprint $table) {
                $table->dropColumn('campana_id');
            });
        }
    }
};
