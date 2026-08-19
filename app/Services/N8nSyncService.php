<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8nSyncService
{
    public function isConfigured(): bool
    {
        $url = config('services.n8n.url');
        $key = config('services.n8n.api_key');

        return $url !== '' && $key !== null && $key !== '';
    }

    /**
     * Sincroniza el system_prompt del agente al nodo indicado del workflow en N8N.
     * Obtiene el workflow, localiza el nodo por id, actualiza el prompt y envía el workflow de vuelta.
     */
    public function syncPromptToWorkflow(Agent $agent): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $workflowId = $agent->n8n_workflow_id;
        $nodeId = $agent->n8n_prompt_node_id;
        $systemPrompt = $agent->prompt_configuration['system_prompt'] ?? '';

        if (empty($workflowId) || empty($nodeId)) {
            return false;
        }

        $baseUrl = config('services.n8n.url');
        $apiKey = config('services.n8n.api_key');

        try {
            $getResponse = Http::withHeaders([
                'X-N8N-API-KEY' => $apiKey,
            ])->get("{$baseUrl}/api/v1/workflows/{$workflowId}");

            if (! $getResponse->successful()) {
                $this->safeLog('warning', 'N8N: no se pudo obtener el workflow', [
                    'workflow_id' => $workflowId,
                    'status' => $getResponse->status(),
                    'body' => $getResponse->body(),
                ]);

                return false;
            }

            $workflow = $getResponse->json();
            $nodes = $workflow['nodes'] ?? [];
            $updated = false;

            foreach ($nodes as $i => $node) {
                if (($node['id'] ?? '') !== $nodeId) {
                    continue;
                }

                $params = $node['parameters'] ?? [];
                if (isset($params['systemMessage'])) {
                    $nodes[$i]['parameters']['systemMessage'] = $systemPrompt;
                    $updated = true;
                    break;
                }
                if (isset($params['options']['systemMessage'])) {
                    $nodes[$i]['parameters']['options']['systemMessage'] = $systemPrompt;
                    $updated = true;
                    break;
                }
                if (isset($params['prompt'])) {
                    $nodes[$i]['parameters']['prompt'] = $systemPrompt;
                    $updated = true;
                    break;
                }
                if (isset($params['text'])) {
                    $nodes[$i]['parameters']['text'] = $systemPrompt;
                    $updated = true;
                    break;
                }
                $nodes[$i]['parameters']['systemMessage'] = $systemPrompt;
                $updated = true;
                break;
            }

            if (! $updated) {
                $this->safeLog('warning', 'N8N: nodo de prompt no encontrado o sin campo editable', [
                    'workflow_id' => $workflowId,
                    'node_id' => $nodeId,
                ]);

                return false;
            }

            $workflow['nodes'] = $nodes;
            $putResponse = Http::withHeaders([
                'X-N8N-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ])->put("{$baseUrl}/api/v1/workflows/{$workflowId}", $workflow);

            if (! $putResponse->successful()) {
                $this->safeLog('warning', 'N8N: no se pudo actualizar el workflow', [
                    'workflow_id' => $workflowId,
                    'status' => $putResponse->status(),
                    'body' => $putResponse->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->safeLog('error', 'N8N: error al sincronizar prompt', [
                'workflow_id' => $workflowId ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Escribe en el log sin lanzar si falla (ej. permisos en storage/logs).
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            if ($level === 'error') {
                Log::error($message, $context);
            } else {
                Log::warning($message, $context);
            }
        } catch (\Throwable) {
            // Evitar que un fallo de escritura del log provoque 500
        }
    }
}
