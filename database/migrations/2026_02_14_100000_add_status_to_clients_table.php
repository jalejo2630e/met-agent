<?php

use App\Models\Client;
use App\Models\ClientCallbackRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->after('loaded_at')
                ->comment('Ej: llamada_programada cuando tiene callback pendiente');
        });

        $clientIds = ClientCallbackRequest::where('status', ClientCallbackRequest::STATUS_PENDING)
            ->pluck('client_id')
            ->unique();
        Client::whereIn('id', $clientIds)->update(['status' => Client::STATUS_LLAMADA_PROGRAMADA]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
