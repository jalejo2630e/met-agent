<?php

use App\Models\Client;
use App\Models\ClientLoadDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $clients = Client::whereNotNull('loaded_at')
            ->whereDoesntHave('loadDates')
            ->get(['id', 'agent_id', 'loaded_at']);

        foreach ($clients as $client) {
            ClientLoadDate::create([
                'client_id' => $client->id,
                'agent_id' => $client->agent_id,
                'loaded_at' => $client->loaded_at,
                'source' => ClientLoadDate::SOURCE_IMPORT,
            ]);
        }
    }

    public function down(): void
    {
        // No eliminar datos; solo el up hace el backfill
    }
};
