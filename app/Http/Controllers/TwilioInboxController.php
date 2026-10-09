<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\ConversationExtraction;
use App\Models\ConversationNote;
use App\Models\ConversationState;
use App\Models\TwilioMessage;
use App\Services\AudioTranscoder;
use App\Services\ConversationExtractionService;
use App\Services\TwilioContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Bandeja de mensajes recibidos por el webhook de Twilio (WhatsApp/SMS).
 * Agrupa por número y marca si ya existe un cliente, para poder crearlo desde ahí.
 * Permite además que un humano tome el control: pausar el bot, responder con texto
 * o plantillas de Meta/Twilio y dejar notas internas (no visibles para el cliente).
 */
class TwilioInboxController extends Controller
{
    public function index(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $messages = TwilioMessage::where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        // Estados de pausa por número (una sola consulta).
        $states = ConversationState::where('agent_id', $agent->id)
            ->pluck('bot_paused', 'from_number');

        $conversations = $messages->groupBy('from_number')->map(function ($msgs, $from) use ($agent, $states) {
            $last = $msgs->first();
            $profile = optional($msgs->firstWhere(fn ($m) => filled($m->profile_name)))->profile_name;
            $client = $this->findClient($agent, (string) $from);

            return [
                'from_number' => $from,
                'profile_name' => $profile,
                'last_message' => [
                    'body' => $last?->body,
                    'direction' => $last?->direction,
                    'created_at' => optional($last?->created_at)->toIso8601String(),
                ],
                'messages_count' => $msgs->count(),
                'inbound_count' => $msgs->where('direction', 'inbound')->count(),
                'bot_paused' => (bool) ($states[$from] ?? false) || (bool) ($client?->ai_paused),
                'client' => $client
                    ? ['id' => $client->id, 'name' => trim($client->name.' '.$client->lastname)]
                    : null,
            ];
        })->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function thread(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('view', $agent);

        $from = (string) $request->query('from', '');

        $messages = TwilioMessage::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'direction', 'body', 'media_url', 'media_type', 'created_at']);

        return response()->json([
            'messages' => $messages,
            'extraction' => $this->extractionPayload($agent, $from),
            'bot_paused' => $this->botPaused($agent, $from),
            'notes' => $this->notesPayload($agent, $from),
        ]);
    }

    /**
     * Pausa/reactiva el bot para una conversación (toma de control humano).
     * Se guarda por número y, si hay cliente, se sincroniza su ai_paused.
     */
    public function pause(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'from' => 'required|string|max:40',
            'paused' => 'required|boolean',
        ]);

        $from = $validated['from'];
        $paused = (bool) $validated['paused'];

        ConversationState::updateOrCreate(
            ['agent_id' => $agent->id, 'from_number' => $from],
            ['bot_paused' => $paused],
        );

        // Mantén el cliente en sincronía para que el panel del cliente coincida.
        $client = $this->findClient($agent, $from);
        if ($client) {
            $client->update(['ai_paused' => $paused]);
        }

        return response()->json(['bot_paused' => $paused]);
    }

    /**
     * Responde como humano con texto libre (mensaje de sesión, ventana de 24h).
     */
    public function reply(Agent $agent, Request $request, TwilioContentService $twilio): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'from' => 'required|string|max:40',
            'body' => 'required|string|max:4000',
        ]);

        if (! $twilio->canSendWhatsapp()) {
            return response()->json(['success' => false, 'message' => 'Twilio no está configurado para enviar WhatsApp (define TWILIO_WHATSAPP_FROM).'], 422);
        }

        try {
            $twilio->sendWhatsappText($validated['from'], $validated['body']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar. Si pasaron más de 24h desde el último mensaje del cliente, reabre con una plantilla. ('.$e->getMessage().')',
            ], 502);
        }

        $message = $this->recordOutbound($agent, $validated['from'], $validated['body']);

        return response()->json(['success' => true, 'message' => 'Mensaje enviado.', 'new_message' => $message]);
    }

    /**
     * Envía una plantilla de Meta/Twilio (Content SID) a la conversación.
     */
    public function sendTemplate(Agent $agent, Request $request, TwilioContentService $twilio): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'from' => 'required|string|max:40',
            'id_plantilla' => 'nullable|string|max:255',
        ]);

        $agent->load('messageConfig');
        $plantillas = $agent->messageConfig?->plantillas ?? [];

        $idPlantilla = $validated['id_plantilla'] ?? null;
        if ($idPlantilla === null || $idPlantilla === '') {
            $idPlantilla = $agent->messageConfig?->default_plantilla_id;
        }
        if ($idPlantilla === null || $idPlantilla === '') {
            return response()->json(['success' => false, 'message' => 'Selecciona una plantilla.'], 422);
        }

        $plantilla = collect($plantillas)->first(fn ($p) => ($p['id'] ?? null) === $idPlantilla);
        $esTwilio = ($plantilla['from_twilio'] ?? false) || str_starts_with((string) $idPlantilla, 'HX');

        if (! $esTwilio || ! $twilio->canSendWhatsapp()) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden enviar plantillas de Twilio desde la bandeja. Configura TWILIO_WHATSAPP_FROM y usa una plantilla de Twilio.',
            ], 422);
        }

        // Si hay cliente, resuelve las variables de la plantilla con sus datos.
        $variablesMap = [];
        $client = $this->findClient($agent, $validated['from']);
        if ($client) {
            $variables = $agent->messageConfig?->resolvePlantillaVariables($idPlantilla, $client, $agent) ?? [];
            foreach ($variables as $v) {
                $variablesMap[(string) ($v['name'] ?? '')] = (string) ($v['value'] ?? '');
            }
        }

        try {
            $twilio->sendWhatsappTemplate($validated['from'], (string) $idPlantilla, $variablesMap);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error al enviar por Twilio: '.$e->getMessage()], 502);
        }

        $label = '📋 Plantilla enviada'.(! empty($plantilla['name']) ? ': '.$plantilla['name'] : '');
        $message = $this->recordOutbound($agent, $validated['from'], $label);

        return response()->json(['success' => true, 'message' => 'Plantilla enviada.', 'new_message' => $message]);
    }

    /**
     * Envía un archivo (imagen, audio, video, PDF) a la conversación por WhatsApp.
     */
    public function sendMedia(Agent $agent, Request $request, TwilioContentService $twilio, AudioTranscoder $transcoder): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'from' => 'required|string|max:40',
            'file' => [
                'required', 'file', 'max:16384',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,audio/mpeg,audio/ogg,audio/aac,audio/mp4,audio/x-m4a,audio/wav,audio/webm,video/mp4,video/webm,video/3gpp,application/pdf',
            ],
            'caption' => ['nullable', 'string', 'max:1000'],
            'voice' => ['nullable', 'boolean'],
        ]);

        if (! $twilio->canSendWhatsapp()) {
            return response()->json(['success' => false, 'message' => 'Twilio no está configurado para enviar WhatsApp (define TWILIO_WHATSAPP_FROM).'], 422);
        }

        $file = $request->file('file');
        $type = (string) $file->getMimeType();

        // WhatsApp rechaza WebM/OGG-Opus; normaliza toda nota de voz a MP3 mono.
        $needsMp3 = $request->boolean('voice')
            || (str_starts_with($type, 'audio/') && $type !== 'audio/mpeg')
            || $type === 'video/webm';

        if ($needsMp3) {
            try {
                [$path, $mime] = $transcoder->toMp3($file->getRealPath());
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'No se pudo convertir el audio a un formato compatible con WhatsApp. Verifica que ffmpeg esté instalado. ('.$e->getMessage().')'], 422);
            }
        } else {
            $path = $file->store('whatsapp-media', 'public');
            $mime = $type;
        }

        $mediaUrl = url(Storage::disk('public')->url($path));
        $caption = $validated['caption'] ?? null;

        try {
            $twilio->sendWhatsappMedia($validated['from'], $mediaUrl, $caption);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el archivo. Si pasaron más de 24h, reabre con una plantilla. ('.$e->getMessage().')',
            ], 502);
        }

        $label = ($caption && trim($caption) !== '') ? $caption : $this->mediaLabel($mime, (string) $file->getClientOriginalName());
        $message = $this->recordOutbound($agent, $validated['from'], $label, $mediaUrl, $mime);

        return response()->json(['success' => true, 'message' => 'Archivo enviado.', 'new_message' => $message]);
    }

    private function mediaLabel(string $mime, string $filename): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => '📷 Imagen',
            str_starts_with($mime, 'audio/') => '🎤 Nota de voz',
            str_starts_with($mime, 'video/') => '🎥 Video',
            default => '📎 '.$filename,
        };
    }

    public function notes(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('view', $agent);

        return response()->json(['notes' => $this->notesPayload($agent, (string) $request->query('from', ''))]);
    }

    public function storeNote(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'from' => 'required|string|max:40',
            'body' => 'required|string|max:5000',
        ]);

        ConversationNote::create([
            'agent_id' => $agent->id,
            'from_number' => $validated['from'],
            'user_id' => $request->user()?->id,
            'body' => $validated['body'],
        ]);

        return response()->json(['notes' => $this->notesPayload($agent, $validated['from'])]);
    }

    public function destroyNote(Agent $agent, ConversationNote $note): JsonResponse
    {
        $this->authorize('update', $agent);
        abort_unless($note->agent_id === $agent->id, 404);

        $from = $note->from_number;
        $note->delete();

        return response()->json(['notes' => $this->notesPayload($agent, $from)]);
    }

    /**
     * Vacía la conversación de un número (solo administrador): borra los mensajes,
     * sus archivos de media guardados y las variables extraídas. El bot usa estos
     * mensajes como memoria, así que también empieza de cero con esa persona.
     * Se conservan las notas internas, el estado de pausa y el cliente.
     */
    public function clear(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'from' => 'required|string|max:40',
        ]);
        $from = $validated['from'];

        $messages = TwilioMessage::where('agent_id', $agent->id)->where('from_number', $from);

        $storagePrefix = rtrim(Storage::disk('public')->url(''), '/').'/';
        $mediaPaths = (clone $messages)->whereNotNull('media_url')->pluck('media_url')
            ->map(function (string $url) use ($storagePrefix) {
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                $prefix = parse_url($storagePrefix, PHP_URL_PATH) ?: '/storage/';

                return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : null;
            })
            ->filter(fn ($p) => $p && str_starts_with($p, 'whatsapp-media/'))
            ->unique()
            ->values();

        $deleted = $messages->delete();

        ConversationExtraction::where('agent_id', $agent->id)->where('from_number', $from)->delete();

        // Solo borra archivos que ningún otro mensaje siga usando.
        foreach ($mediaPaths as $path) {
            $stillUsed = TwilioMessage::where('media_url', 'like', '%/'.$path)->exists();
            if (! $stillUsed) {
                Storage::disk('public')->delete($path);
            }
        }

        return response()->json([
            'deleted' => $deleted,
            'message' => "Conversación vaciada ({$deleted} mensajes eliminados).",
        ]);
    }

    /**
     * Fuerza una nueva extracción de variables de recolección para la conversación.
     */
    public function extract(Agent $agent, Request $request, ConversationExtractionService $service): JsonResponse
    {
        $this->authorize('view', $agent);

        $from = (string) $request->input('from', '');
        if ($from !== '') {
            $service->extractForConversation($agent, $from);
        }

        return response()->json(['extraction' => $this->extractionPayload($agent, $from)]);
    }

    /**
     * Registra el mensaje saliente en el historial usando la misma clave de
     * agrupación (from_number) para que aparezca en el hilo de la conversación.
     */
    private function recordOutbound(Agent $agent, string $from, string $body, ?string $mediaUrl = null, ?string $mediaType = null): array
    {
        $msg = TwilioMessage::create([
            'agent_id' => $agent->id,
            'channel' => 'whatsapp',
            'sede' => TwilioMessage::sedeFor($agent->id, $from),
            'from_number' => $from,
            'direction' => 'outbound',
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        return [
            'id' => $msg->id,
            'direction' => $msg->direction,
            'body' => $msg->body,
            'media_url' => $msg->media_url,
            'media_type' => $msg->media_type,
            'created_at' => $msg->created_at?->toIso8601String(),
        ];
    }

    private function botPaused(Agent $agent, string $from): bool
    {
        $state = ConversationState::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->value('bot_paused');

        return (bool) $state || (bool) $this->findClient($agent, $from)?->ai_paused;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notesPayload(Agent $agent, string $from): array
    {
        if ($from === '') {
            return [];
        }

        return ConversationNote::with('user:id,name')
            ->where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->latest()
            ->get()
            ->map(fn (ConversationNote $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'user' => $n->user?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all();
    }

    /**
     * Variables de recolección definidas + valores extraídos de la conversación.
     *
     * @return array{variables: array<int, array<string, mixed>>, values: array<string, mixed>, updated_at: ?string}
     */
    private function extractionPayload(Agent $agent, string $from): array
    {
        $variables = $agent->extractionVariables()->orderBy('order')->orderBy('id')->get()
            ->map(fn ($v) => [
                'name' => $v->name,
                'label' => $v->label,
                'description' => $v->description,
                'type' => $v->type,
            ])->values();

        $extraction = ConversationExtraction::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->first();

        return [
            'variables' => $variables->all(),
            'values' => (array) ($extraction?->values ?? []),
            'updated_at' => $extraction?->updated_at?->toIso8601String(),
        ];
    }

    private function findClient(Agent $agent, string $from): ?Client
    {
        $digits = preg_replace('/\D/', '', $from);
        if ($digits === '') {
            return null;
        }
        $last10 = substr($digits, -10);

        return Client::where('agent_id', $agent->id)
            ->where(function ($q) use ($digits, $last10) {
                $q->where('phone', $digits)->orWhere('phone', 'like', '%'.$last10);
            })
            ->first();
    }
}
