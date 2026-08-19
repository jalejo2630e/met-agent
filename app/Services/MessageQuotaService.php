<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentWhatsappConversation;
use App\Observers\AgentObserver;
use App\Support\WhatsappConversationsConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Consumo de mensajes de WhatsApp del mes calendario actual (para el tope mensual).
 * Suma todos los agentes leyendo desde Supabase (REST, con filtro por columna de fecha)
 * o desde la BD interna, según el origen configurado. Precisa: cuenta filas reales.
 */
class MessageQuotaService
{
    /**
     * Umbrales de alerta como porcentaje del tope.
     */
    public const THRESHOLDS = [50, 80, 100];

    public function __construct(
        protected SupabaseRestClient $rest
    ) {}

    /**
     * Total de mensajes registrados en el mes calendario actual (todos los agentes).
     */
    public function currentMonthMessageCount(): int
    {
        [$start, $end] = $this->currentMonthRange();

        return WhatsappConversationsConnection::readsViaRest()
            ? $this->countViaRest($start, $end)
            : $this->countInternal($start, $end);
    }

    /**
     * Inicio y fin del mes actual en formato 'Y-m-d H:i:s'.
     *
     * @return array{0: string, 1: string}
     */
    public function currentMonthRange(): array
    {
        $now = Carbon::now();

        return [
            $now->copy()->startOfMonth()->format('Y-m-d H:i:s'),
            $now->copy()->endOfMonth()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Clave del mes actual (Y-m), para deduplicar alertas por mes.
     */
    public function currentMonthKey(): string
    {
        return Carbon::now()->format('Y-m');
    }

    protected function countViaRest(string $start, string $end): int
    {
        $dateColumn = WhatsappConversationsConnection::supabaseDateColumn();
        $total = 0;

        foreach (Agent::all() as $agent) {
            $table = WhatsappConversationsConnection::supabaseTableNameForAgent($agent->id);
            $query = $dateColumn !== ''
                ? ['and' => '('.$dateColumn.'.gte.'.$start.','.$dateColumn.'.lte.'.$end.')']
                : [];

            try {
                $total += $this->rest->count($table, $query);
            } catch (\Throwable $e) {
                Log::warning('[MessageQuotaService] No se pudo contar mensajes del mes en '.$table.': '.$e->getMessage());
            }
        }

        return $total;
    }

    protected function countInternal(string $start, string $end): int
    {
        $connection = WhatsappConversationsConnection::eloquentConnectionName();
        $total = 0;

        foreach (Agent::all() as $agent) {
            $table = AgentObserver::getTableName($agent->id);
            if (! Schema::connection($connection)->hasTable($table)) {
                continue;
            }

            $total += AgentWhatsappConversation::forAgent($agent)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        return $total;
    }
}
