<?php

namespace App\Services;

use App\Models\Agent;

/**
 * Conversaciones WhatsApp por agente desde Supabase REST (nombre de tabla vía patrón en ajustes).
 */
class SupabaseWhatsappMessagesRestService
{
    public function __construct(
        protected SupabaseRestClient $client
    ) {}

    /**
     * Filas crudas (id, message, created_at) filtradas por la columna configurada (p. ej. phone o session_id)
     * con el teléfono del cliente normalizado a dígitos.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchRows(Agent $agent, string $sessionPhone): array
    {
        $filterValue = preg_replace('/\D/', '', $sessionPhone);
        if ($filterValue === '') {
            $filterValue = trim($sessionPhone);
        }
        if ($filterValue === '') {
            return [];
        }

        $table = \App\Support\WhatsappConversationsConnection::supabaseTableNameForAgent($agent->id);
        $column = \App\Support\WhatsappConversationsConnection::supabasePhoneFilterColumn();

        $query = [
            'select' => \App\Support\WhatsappConversationsConnection::supabaseSelectColumns(),
            'order' => 'id.asc',
        ];
        $query[$column] = 'eq.'.$filterValue;

        return $this->client->select($table, $query);
    }
}
