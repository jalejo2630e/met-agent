<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente conversacional nativo (Laravel AI SDK) que atiende mensajes de
 * WhatsApp/SMS entrantes desde Twilio, usando el system_prompt configurado del
 * agente en el panel. Reemplaza la orquestación externa en n8n para el canal
 * de texto: el prompt se edita "aquí" y la IA corre dentro de Laravel.
 *
 * El historial ($history) se inyecta como contexto para dar memoria a la
 * conversación por número de teléfono.
 */
class WhatsappAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * @param  string  $systemPrompt  system_prompt del agente (campo único de prompt_configuration).
     * @param  list<array{role: string, content: string}>  $history  Turnos previos.
     */
    public function __construct(
        public string $systemPrompt,
        public array $history = [],
    ) {
    }

    public function instructions(): Stringable|string
    {
        return $this->systemPrompt !== ''
            ? $this->systemPrompt
            : 'Eres un asistente de atención al cliente. Responde de forma breve y cordial en español.';
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        $messages = [];

        foreach ($this->history as $turn) {
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $messages[] = ($turn['role'] ?? 'user') === 'assistant'
                ? new AssistantMessage($content)
                : new UserMessage($content);
        }

        return $messages;
    }
}
