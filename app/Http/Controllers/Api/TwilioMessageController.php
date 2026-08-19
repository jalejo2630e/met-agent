<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\WhatsappAgent;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\TwilioMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de Twilio (WhatsApp/SMS) para atender mensajes con un agente de IA
 * NATIVO de Laravel — reemplaza la orquestación en n8n para el canal de texto.
 *
 * Twilio debe apuntar "A MESSAGE COMES IN" a:
 *   https://TU_DOMINIO/api/agents/{agent_id}/twilio/whatsapp   (POST)
 *
 * El prompt del agente se toma de su configuración en el panel
 * (Agent.prompt_configuration.system_prompt). Responde con TwiML.
 */
class TwilioMessageController extends Controller
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        $token = (string) config('services.twilio.auth_token', '');
        if ($token !== '' && ! $this->validSignature($request, $token)) {
            return response('Firma de Twilio inválida.', 403);
        }

        $fromRaw = (string) $request->input('From', '');
        $channel = str_starts_with($fromRaw, 'whatsapp:') ? 'whatsapp' : 'sms';
        $from = trim(str_replace('whatsapp:', '', $fromRaw));
        $body = trim((string) $request->input('Body', ''));

        if ($from === '' || $body === '') {
            return $this->twiml('');
        }

        // Historial previo (antes de guardar el mensaje entrante) para dar memoria.
        $history = TwilioMessage::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn (TwilioMessage $m): array => [
                'role' => $m->direction === 'outbound' ? 'assistant' : 'user',
                'content' => (string) $m->body,
            ])
            ->values()
            ->all();

        TwilioMessage::create([
            'agent_id' => $agent->id,
            'channel' => $channel,
            'from_number' => $from,
            'direction' => 'inbound',
            'body' => $body,
            'message_sid' => $request->input('MessageSid'),
            'profile_name' => $request->input('ProfileName'),
        ]);

        $reply = '';

        if (! $this->aiConfigured()) {
            Log::warning('Twilio webhook: proveedor de IA no configurado (OPENAI_API_KEY).');
        } else {
            try {
                $systemPrompt = (string) data_get($agent->prompt_configuration, 'system_prompt', '');
                $response = (new WhatsappAgent($systemPrompt, $history))->prompt($body);
                $reply = trim((string) $response);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($reply !== '') {
            TwilioMessage::create([
                'agent_id' => $agent->id,
                'channel' => $channel,
                'from_number' => $from,
                'direction' => 'outbound',
                'body' => $reply,
            ]);
        }

        return $this->twiml($reply);
    }

    /**
     * Valida la firma X-Twilio-Signature (HMAC-SHA1 de URL + params ordenados).
     */
    private function validSignature(Request $request, string $token): bool
    {
        $signature = (string) $request->header('X-Twilio-Signature', '');
        if ($signature === '') {
            return false;
        }

        $data = $request->fullUrl();
        $params = $request->post();
        ksort($params);
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, $signature);
    }

    private function aiConfigured(): bool
    {
        return (string) config('ai.providers.'.config('ai.default').'.key', '') !== '';
    }

    private function twiml(string $message): Response
    {
        $inner = $message === ''
            ? '<Response></Response>'
            : '<Response><Message>'.htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Message></Response>';

        return response('<?xml version="1.0" encoding="UTF-8"?>'.$inner, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }
}
