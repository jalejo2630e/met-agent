<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCallTranscriptJob;
use App\Models\Agent;
use App\Models\RegistroAudioLlamada;
use App\Models\RegistroEscritoLlamada;
use App\Services\CallAnalysisService;
use App\Services\CallBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook Post-Call de ElevenLabs. Recibe la transcripción (y audio) de cada
 * llamada, los guarda, dispara el análisis de IA y LIBERA el cupo en la cola de
 * llamadas para que se ejecute la siguiente (ventana deslizante).
 *
 * Configúralo en ElevenLabs (Conversational AI → Post-call webhook):
 *   https://TU_DOMINIO/api/agents/{agent_id}/elevenlabs/post-call
 */
class PostCallWebhookController extends Controller
{
    public function __invoke(Request $request, Agent $agent, CallBatchService $batch): JsonResponse
    {
        $secret = (string) config('services.elevenlabs.webhook_secret', '');
        if ($secret !== '' && ! $this->validSignature($request, $secret)) {
            return response()->json(['error' => 'Firma inválida'], 403);
        }

        $payload = $request->all();
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $conversationId = $data['conversation_id'] ?? ($payload['conversation_id'] ?? null);
        $dynamicVars = $data['conversation_initiation_client_data']['dynamic_variables'] ?? [];
        $queueCallId = isset($dynamicVars['queue_call_id']) ? (int) $dynamicVars['queue_call_id'] : null;

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $phone = $metadata['phone_call']['external_number']
            ?? $metadata['phone_number']
            ?? ($dynamicVars['phone'] ?? null);
        $duration = $metadata['call_duration_secs'] ?? null;
        $cost = $metadata['cost'] ?? ($metadata['charging']['call_charge'] ?? null);
        $summary = $data['analysis']['transcript_summary'] ?? null;

        $transcript = $data['transcript'] ?? null;       // array de turnos
        $audioBase64 = $data['full_audio'] ?? ($payload['full_audio'] ?? null);

        // Guardar transcripción (best-effort; puede escribir en la BD interna).
        if ($conversationId && (is_array($transcript) || is_string($transcript))) {
            try {
                RegistroEscritoLlamada::create([
                    'agent_id' => (string) $agent->id,
                    'conversation_id' => (string) $conversationId,
                    'status' => (string) ($data['status'] ?? 'done'),
                    'transcript' => is_array($transcript) ? json_encode($transcript) : $transcript,
                    'phone' => $phone ? preg_replace('/\D/', '', (string) $phone) : null,
                    'summary' => $summary,
                    'costo' => is_numeric($cost) ? $cost : null,
                    'duration_call_seg' => is_numeric($duration) ? (int) $duration : null,
                    'variables_extraidas' => $dynamicVars ? json_encode($dynamicVars) : null,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($conversationId && is_string($audioBase64) && $audioBase64 !== '') {
            try {
                RegistroAudioLlamada::create([
                    'conversation_id' => (string) $conversationId,
                    'audio' => $audioBase64,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Análisis de IA opcional (reutiliza el POC de análisis de llamadas).
        if ($conversationId && is_array($transcript) && app(CallAnalysisService::class)->isConfigured()) {
            AnalyzeCallTranscriptJob::dispatch(
                $agent->id,
                (string) $conversationId,
                (string) json_encode($transcript),
                $phone ? (string) $phone : null,
            );
        }

        // Liberar el cupo en la cola de llamadas y despachar la siguiente.
        try {
            $batch->handlePostCall(
                $agent->id,
                $conversationId ? (string) $conversationId : null,
                $phone ? (string) $phone : null,
                $queueCallId,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Valida la firma HMAC-SHA256 de ElevenLabs: header "t=<ts>,v0=<hash>",
     * hash = HMAC-SHA256("{t}.{body}", secret).
     */
    private function validSignature(Request $request, string $secret): bool
    {
        $header = (string) ($request->header('ElevenLabs-Signature') ?? $request->header('elevenlabs-signature') ?? '');
        if ($header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $kv) {
            $piece = explode('=', $kv, 2);
            if (count($piece) === 2) {
                $parts[trim($piece[0])] = trim($piece[1]);
            }
        }

        $t = $parts['t'] ?? null;
        $v0 = $parts['v0'] ?? null;
        if (! $t || ! $v0) {
            return false;
        }

        $expected = hash_hmac('sha256', $t.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $v0);
    }
}
