<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentWhatsappConversation;
use App\Models\Client;
use App\Models\ClientContactLog;
use App\Models\RegistroEscritoLlamada;
use App\Observers\AgentObserver;
use App\Services\SupabaseCallTranscriptsRestService;
use App\Services\SupabaseRestClient;
use App\Support\CallTranscriptsConnection;
use App\Support\WhatsappConversationsConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $agentsCount = Agent::count();
        $clientsCount = Client::count();
        $whatsappContactsCount = ClientContactLog::where('channel', ClientContactLog::CHANNEL_WHATSAPP)->count();
        $callContactsCount = ClientContactLog::where('channel', ClientContactLog::CHANNEL_CALL)->count();

        $totalMessages = 0;
        if (WhatsappConversationsConnection::readsViaRest()) {
            $rest = app(SupabaseRestClient::class);
            foreach (Agent::all() as $agent) {
                $tableName = WhatsappConversationsConnection::supabaseTableNameForAgent($agent->id);
                try {
                    $totalMessages += $rest->count($tableName);
                } catch (\Throwable) {
                    // Tabla inexistente o sin acceso REST
                }
            }
        } else {
            $whatsappConn = WhatsappConversationsConnection::eloquentConnectionName();
            foreach (Agent::all() as $agent) {
                $tableName = AgentObserver::getTableName($agent->id);
                if (Schema::connection($whatsappConn)->hasTable($tableName)) {
                    $totalMessages += AgentWhatsappConversation::forAgent($agent)->count();
                }
            }
        }

        if (CallTranscriptsConnection::usesRest()) {
            try {
                $totalSeconds = app(SupabaseCallTranscriptsRestService::class)->sumDurationSecondsForCampana();
            } catch (\Throwable) {
                $totalSeconds = 0;
            }
        } else {
            $totalSeconds = RegistroEscritoLlamada::query()->forCampanaConfig()->sum('duration_call_seg');
        }
        $totalMinutes = round($totalSeconds / 60, 1);

        return Inertia::render('Dashboard', [
            'stats' => [
                'agents' => $agentsCount,
                'clients' => $clientsCount,
                'whatsapp_contacts' => $whatsappContactsCount,
                'call_contacts' => $callContactsCount,
                'total_messages' => $totalMessages,
                'total_minutes' => $totalMinutes,
            ],
        ]);
    }
}
