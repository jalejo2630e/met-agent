<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ClientContactLog;

/**
 * Arma los datos del reporte de operación de un agente (clientes, mensajes,
 * llamadas y datos recolectados) para un período opcional. Lo usan tanto el
 * endpoint JSON del panel como el envío por correo.
 */
class AgentOperationReportService
{
    public function __construct(
        protected CallCountService $calls
    ) {}

    /**
     * @return array{
     *   total_clients: int,
     *   total_messages: int,
     *   total_calls: int,
     *   total_with_collection: int,
     *   collection_clients: list<array<string, mixed>>,
     *   period: array{all_time: bool, date_from: string|null, date_to: string|null}
     * }
     */
    public function build(Agent $agent, ?string $dateFrom, ?string $dateTo): array
    {
        $allTime = ! $dateFrom && ! $dateTo;
        $start = (! $allTime && $dateFrom) ? ($dateFrom.' 00:00:00') : null;
        $end = (! $allTime && $dateTo) ? ($dateTo.' 23:59:59') : null;

        $clientsQuery = $agent->clients();
        $messagesQuery = ClientContactLog::where('agent_id', $agent->id)->where('channel', ClientContactLog::CHANNEL_WHATSAPP);

        if ($start) {
            $clientsQuery->where(function ($q) use ($start) {
                $q->whereHas('loadDates', fn ($q2) => $q2->where('loaded_at', '>=', $start))
                    ->orWhere(fn ($q2) => $q2->whereDoesntHave('loadDates')->where('created_at', '>=', $start));
            });
            $messagesQuery->where('contacted_at', '>=', $start);
        }
        if ($end) {
            $clientsQuery->where(function ($q) use ($end) {
                $q->whereHas('loadDates', fn ($q2) => $q2->where('loaded_at', '<=', $end))
                    ->orWhere(fn ($q2) => $q2->whereDoesntHave('loadDates')->where('created_at', '<=', $end));
            });
            $messagesQuery->where('contacted_at', '<=', $end);
        }

        $totalClients = $clientsQuery->count();

        $withCollectionQuery = $agent->clients()->withCollectionData();
        if ($start) {
            $withCollectionQuery->where('updated_at', '>=', $start);
        }
        if ($end) {
            $withCollectionQuery->where('updated_at', '<=', $end);
        }
        $totalWithCollection = $withCollectionQuery->count();

        // Total de llamadas desde la tabla de registros (Supabase / interna), asociadas
        // por el teléfono de los clientes del agente.
        $clientPhones = $agent->clients()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('phone');
        $totalCalls = $this->calls->totalForPhones($clientPhones, $start, $end);

        $collectionClientsQuery = $agent->clients()->withCollectionData();
        if ($start && $end) {
            $collectionClientsQuery->whereBetween('updated_at', [$start, $end]);
        }
        $collectionClients = $collectionClientsQuery->withCount('contactLogs')
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get(['id', 'name', 'lastname', 'email', 'phone', 'custom_fields', 'updated_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'lastname' => $c->lastname,
                'email' => $c->email,
                'phone' => $c->phone,
                'custom_fields' => $c->custom_fields ?? [],
                'updated_at' => $c->updated_at?->toIso8601String(),
                'contact_count' => $c->contact_logs_count ?? 0,
            ])
            ->values()
            ->all();

        return [
            'total_clients' => $totalClients,
            'total_messages' => $messagesQuery->count(),
            'total_calls' => $totalCalls,
            'total_with_collection' => $totalWithCollection,
            'collection_clients' => $collectionClients,
            'period' => [
                'all_time' => $allTime,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ];
    }
}
