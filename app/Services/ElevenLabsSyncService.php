<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Http;

class ElevenLabsSyncService
{
    private const BASE_URL = 'https://api.elevenlabs.io';

    public function isConfigured(): bool
    {
        $key = config('services.elevenlabs.api_key');

        return $key !== null && $key !== '';
    }

    /**
     * Envía el system_prompt del agente al agente de ElevenLabs indicado por agent_id.
     */
    public function syncPromptToAgent(string $elevenlabsAgentId, string $systemPrompt): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $apiKey = config('services.elevenlabs.api_key');

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->patch(self::BASE_URL . '/v1/convai/agents/' . $elevenlabsAgentId, [
                'conversation_config' => [
                    'agent' => [
                        'prompt' => [
                            'prompt' => $systemPrompt,
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                $this->safeLog('warning', 'ElevenLabs: no se pudo actualizar el agente', [
                    'agent_id' => $elevenlabsAgentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->safeLog('error', 'ElevenLabs: error al sincronizar prompt', [
                'agent_id' => $elevenlabsAgentId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            if ($level === 'error') {
                \Illuminate\Support\Facades\Log::error($message, $context);
            } else {
                \Illuminate\Support\Facades\Log::warning($message, $context);
            }
        } catch (\Throwable) {
        }
    }
}
