<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\WhatsappAgent;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AiUsageLog;
use App\Models\Client;
use App\Models\ConversationState;
use App\Models\TwilioMessage;
use App\Services\AgentKnowledgeService;
use App\Services\AudioTranscoder;
use App\Services\ConversationExtractionService;
use App\Services\SedeResolver;
use App\Services\TwilioContentService;
use App\Services\WhatsappClientService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Transcription;

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
    /** Modelo de IA por defecto para las respuestas de WhatsApp. */
    private const DEFAULT_MODEL = 'gpt-4.1-mini';

    public function __invoke(Request $request, Agent $agent): Response
    {
        $token = (string) config('services.twilio.auth_token', '');
        if ($token !== '' && ! $this->validSignature($request, $token)) {
            return response('Firma de Twilio inválida.', 403);
        }

        $fromRaw = (string) $request->input('From', '');
        $channel = str_starts_with($fromRaw, 'whatsapp:') ? 'whatsapp' : 'sms';
        $from = trim(str_replace('whatsapp:', '', $fromRaw));
        $to = trim(str_replace('whatsapp:', '', (string) $request->input('To', '')));
        $sede = SedeResolver::fromNumber($to);
        $body = trim((string) $request->input('Body', ''));
        $numMedia = (int) $request->input('NumMedia', 0);

        // Nada que procesar si no hay texto ni archivos adjuntos.
        if ($from === '' || ($body === '' && $numMedia < 1)) {
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

        // Descarga y guarda la media entrante (nota de voz, imagen, etc.) para
        // poder previsualizarla en la bandeja. Best-effort.
        [$mediaUrl, $mediaType, $mediaPath] = $numMedia > 0 ? $this->storeInboundMedia($request) : [null, null, null];

        // Si el mensaje es solo media (sin texto), intenta entenderla para que la
        // IA sepa qué contestar: audio -> transcripción, imagen -> descripción.
        $understood = ($body === '' && $mediaPath !== null && $mediaType !== null)
            ? $this->understandMedia($mediaPath, $mediaType, $agent->id, $from)
            : '';

        // Texto que procesa la IA: el del cliente o lo entendido de la media.
        $aiInput = $body !== '' ? $body : $understood;

        // Cuerpo que se guarda/muestra en la bandeja.
        $storedBody = $body !== '' ? $body
            : ($understood !== '' ? $understood : ($mediaType ? $this->mediaLabel($mediaType) : ''));

        TwilioMessage::create([
            'agent_id' => $agent->id,
            'channel' => $channel,
            'sede' => $sede,
            'from_number' => $from,
            'to_number' => $to !== '' ? $to : null,
            'direction' => 'inbound',
            'body' => $storedBody,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'message_sid' => $request->input('MessageSid'),
            'profile_name' => $request->input('ProfileName'),
        ]);

        // Quien escribe por primera vez queda registrado como cliente (best-effort).
        try {
            app(WhatsappClientService::class)->ensure($agent, $from, (string) $request->input('ProfileName', ''));
        } catch (\Throwable $e) {
            report($e);
        }

        $reply = '';

        // Toma de control humano: si el cliente tiene la IA pausada, o si la
        // conversación fue pausada desde la bandeja, no se responde automáticamente.
        $aiPaused = $this->clientAiPaused($agent, $from) || $this->conversationPaused($agent, $from);

        if ($aiPaused) {
            Log::info('Twilio webhook: IA pausada para el cliente; no se responde automáticamente.', [
                'agent_id' => $agent->id,
                'from' => $from,
            ]);
        } elseif ($aiInput === '') {
            // Media que no se pudo entender (sticker, transcripción vacía…): queda
            // en la bandeja para que un humano la atienda.
        } elseif (! $this->aiConfigured()) {
            Log::warning('Twilio webhook: proveedor de IA no configurado (OPENAI_API_KEY).');
        } else {
            try {
                $systemPrompt = (string) data_get($agent->prompt_configuration, 'system_prompt', '');
                // Inyecta la base de conocimiento habilitada del agente como contexto.
                $knowledge = app(AgentKnowledgeService::class)->promptContext($agent);
                if ($knowledge !== '') {
                    $systemPrompt = trim($systemPrompt."\n\n".$knowledge);
                }
                $provider = data_get($agent->prompt_configuration, 'ai_provider') ?: null;
                // Modelo por defecto: gpt-4.1-mini (si el agente no define uno).
                $model = data_get($agent->prompt_configuration, 'ai_model') ?: self::DEFAULT_MODEL;
                $response = (new WhatsappAgent($systemPrompt, $history))->prompt($aiInput, provider: $provider, model: $model);
                $reply = trim((string) $response);
                AiUsageLog::record($agent->id, $from, 'message', $response->meta->model ?? $model, $response->usage->promptTokens, $response->usage->completionTokens);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($reply !== '') {
            TwilioMessage::create([
                'agent_id' => $agent->id,
                'channel' => $channel,
                'sede' => $sede,
                'from_number' => $from,
                'to_number' => $to !== '' ? $to : null,
                'direction' => 'outbound',
                'body' => $reply,
            ]);
        }

        // Extrae las variables de recolección definidas para el agente (best-effort).
        try {
            app(ConversationExtractionService::class)->extractForConversation($agent, $from);
        } catch (\Throwable $e) {
            report($e);
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

    /**
     * Descarga la primera media entrante de Twilio, la guarda en el disco public
     * y (si es audio no-MP3) la transcodifica a MP3 para reproducción universal.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [url pública, content-type, ruta local]
     */
    private function storeInboundMedia(Request $request): array
    {
        $url = (string) $request->input('MediaUrl0', '');
        if ($url === '') {
            return [null, null, null];
        }

        $declared = trim(explode(';', (string) $request->input('MediaContentType0', ''))[0]);

        $downloaded = app(TwilioContentService::class)->downloadMedia($url);
        if ($downloaded === null) {
            return [null, null, null];
        }
        [$contents, $headerType] = $downloaded;
        $type = $declared !== '' ? $declared : (trim(explode(';', $headerType)[0]) ?: 'application/octet-stream');

        try {
            $path = 'whatsapp-media/in/'.Str::uuid()->toString().'.'.$this->extForType($type);
            Storage::disk('public')->put($path, $contents);

            // WhatsApp manda las notas de voz en OGG/Opus; Safari no lo reproduce.
            if (str_starts_with($type, 'audio/') && $type !== 'audio/mpeg') {
                try {
                    [$mp3Path, $mp3Mime] = app(AudioTranscoder::class)->toMp3(Storage::disk('public')->path($path));
                    Storage::disk('public')->delete($path);
                    $path = $mp3Path;
                    $type = $mp3Mime;
                } catch (\Throwable) {
                    // Si ffmpeg falla, se conserva el original (audible en Chrome/Firefox).
                }
            }

            return [url(Storage::disk('public')->url($path)), $type, Storage::disk('public')->path($path)];
        } catch (\Throwable $e) {
            report($e);

            return [null, null, null];
        }
    }

    /**
     * Convierte la media entrante en texto para que la IA sepa qué contestar:
     * transcribe el audio (Whisper) y describe las imágenes (visión). Los
     * stickers de WhatsApp (image/webp) se ignoran para no gastar tokens.
     */
    private function understandMedia(string $absPath, string $mime, int $agentId, ?string $from): string
    {
        try {
            if (str_starts_with($mime, 'audio/')) {
                $resp = Transcription::fromPath($absPath)->language('es')->timeout(60)->generate();
                $seconds = app(AudioTranscoder::class)->durationSeconds($absPath);
                AiUsageLog::record($agentId, $from, 'audio', $resp->meta->model ?? 'whisper-1', $resp->usage->promptTokens, $resp->usage->completionTokens, $seconds);

                return trim((string) $resp->text);
            }

            // Imágenes con visión, EXCEPTO stickers de WhatsApp (webp): no se leen.
            if (str_starts_with($mime, 'image/') && $mime !== 'image/webp') {
                $describer = new AnonymousAgent(
                    'Eres un asistente de atención al cliente. Describe en español, en 1-2 frases, qué muestra la imagen que envió el cliente, enfocándote en lo relevante para poder responderle.',
                    [], []
                );
                $resp = $describer->prompt(
                    'Describe brevemente esta imagen enviada por el cliente.',
                    attachments: [Image::fromPath($absPath, $mime)],
                    model: self::DEFAULT_MODEL,
                );
                AiUsageLog::record($agentId, $from, 'image', $resp->meta->model ?? self::DEFAULT_MODEL, $resp->usage->promptTokens, $resp->usage->completionTokens);

                $desc = trim((string) $resp);

                return $desc !== '' ? '[Imagen del cliente] '.$desc : '';
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return '';
    }

    private function mediaLabel(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => '📷 Imagen',
            str_starts_with($mime, 'audio/') => '🎤 Nota de voz',
            str_starts_with($mime, 'video/') => '🎥 Video',
            default => '📎 Archivo',
        };
    }

    private function extForType(string $type): string
    {
        return match ($type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/amr' => 'amr',
            'audio/mp4', 'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'audio/wav' => 'wav',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * ¿La conversación fue pausada desde la bandeja (toma de control humano)?
     * Funciona aunque el número no tenga cliente.
     */
    private function conversationPaused(Agent $agent, string $from): bool
    {
        return (bool) ConversationState::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->value('bot_paused');
    }

    /**
     * ¿El cliente que escribe tiene la IA pausada? Empareja por teléfono
     * (exacto, solo dígitos o por los últimos 10 dígitos).
     */
    private function clientAiPaused(Agent $agent, string $from): bool
    {
        $digits = preg_replace('/\D/', '', $from);
        if ($digits === '') {
            return false;
        }
        $last10 = substr($digits, -10);

        $client = Client::where('agent_id', $agent->id)
            ->where(function ($q) use ($from, $digits, $last10) {
                $q->where('phone', $from)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$last10);
            })
            ->first(['id', 'ai_paused']);

        return (bool) ($client?->ai_paused);
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
