<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\AgentFormResponse;
use App\Models\Client;

class ClientObserver
{
    /**
     * Agentes ya resueltos en este proceso (evita N consultas del agente en importaciones).
     * Solo se cachea la instancia; la verificación de preguntas se hace en fresco en
     * AgentFormResponse::urlForClient() para no quedar con estado obsoleto en workers.
     *
     * @var array<int, Agent|null>
     */
    private static array $agents = [];

    /**
     * Al registrar un cliente nuevo, crea automáticamente el enlace del
     * formulario que diligenciará (si el agente tiene preguntas configuradas).
     * Cubre todos los orígenes: alta manual, API, importación y duplicado.
     */
    public function created(Client $client): void
    {
        $agentId = $client->agent_id;
        if (! $agentId) {
            return;
        }

        if (! array_key_exists($agentId, self::$agents)) {
            self::$agents[$agentId] = $client->relationLoaded('agent')
                ? $client->getRelation('agent')
                : Agent::find($agentId);
        }

        $agent = self::$agents[$agentId];
        if ($agent) {
            // Crea (si no existe) el AgentFormResponse con su token/ruta pública.
            // Devuelve null y no crea nada si el agente aún no tiene preguntas.
            AgentFormResponse::urlForClient($agent, $client);
        }
    }
}
