<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_queues', function (Blueprint $table) {
            $table->json('client_selection_rules')->nullable()->after('client_ids');
        });
    }

    public function down(): void
    {
        Schema::table('contact_queues', function (Blueprint $table) {
            $table->dropColumn('client_selection_rules');
        });
    }
};
