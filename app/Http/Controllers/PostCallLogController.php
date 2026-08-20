<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\PostCallWebhookLog;
use App\Models\RegistroAudioLlamada;
use Illuminate\Http\JsonResponse;

/**
 * Sirve el audio (base64) de un evento del log Post-Call bajo demanda, para no
 * cargar el base64 gigante junto con la lista de logs.
 *
 * El audio SIEMPRE se lee de la BD local (registro_audio_llamadas): ElevenLabs
 * lo envía al webhook —normalmente en un evento post_call_audio aparte— y se
 * guarda por conversation_id. Por eso se busca por conversation_id y no por el
 * evento concreto (el evento de transcripción no trae audio).
 */
class PostCallLogController extends Controller
{
    public function audio(Agent $agent, PostCallWebhookLog $log): JsonResponse
    {
        $this->authorize('view', $agent);

        if ((int) $log->agent_id !== (int) $agent->id) {
            abort(404);
        }

        $conversationId = trim((string) $log->conversation_id);
        if ($conversationId === '') {
            return response()->json(['message' => 'El evento no tiene conversation_id', 'audio' => null], 422);
        }

        $row = RegistroAudioLlamada::where('conversation_id', $conversationId)
            ->whereNotNull('audio')->where('audio', '!=', '')
            ->first(['audio']);
        $audio = $row?->audio;

        if ($audio === null || $audio === '') {
            return response()->json(['message' => 'No hay audio para esta conversación', 'audio' => null]);
        }

        return response()->json(['audio' => $audio]);
    }
}
