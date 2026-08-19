<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_queues', function (Blueprint $table) {
            // Máximo de llamadas en curso simultáneas (ventana deslizante liberada por el post-call).
            $table->unsignedSmallInteger('concurrency')->default(10)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('contact_queues', function (Blueprint $table) {
            $table->dropColumn('concurrency');
        });
    }
};
