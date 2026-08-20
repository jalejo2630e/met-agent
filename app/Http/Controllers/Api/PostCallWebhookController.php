<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\PostCallWebhookLog;
use App\Models\RegistroAudioLlamada;
use App\Models\RegistroEscritoLlamada;
use App\Services\CallBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook Post-Call de ElevenLabs. Recibe la transcripción, el audio y el
 * análisis (que ElevenLabs ya calcula por su cuenta: data_collection y
 * evaluation_criteria) de cada llamada, los guarda y LIBERA el cupo en la cola
 * de llamadas para que se ejecute la siguiente (ventana deslizante).
 *
 * NO ejecuta análisis de IA propio: solo persiste y pinta las variables que
 * envía ElevenLabs. El análisis de IA de Laravel queda disponible aparte
 * (agents.call-analysis.analyze y el comando calls:analyze) por si se necesita.
 *
 * Además registra TODO evento recibido en post_call_webhook_logs (payload crudo
 * sin el audio, que se enlaza por conversation_id) para depuración.
 *
 * Configúralo en ElevenLabs (Conversational AI → Post-call webhook):
 *   https://TU_DOMINIO/api/agents/{agent_id}/elevenlabs/post-call
 */
class PostCallWebhookController extends Controller
{
    public function __invoke(Request $request, Agent $agent, CallBatchService $batch): JsonResponse
    {
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

        $analysis = is_array($data['analysis'] ?? null) ? $data['analysis'] : [];
        $summary = $analysis['transcript_summary'] ?? null;

        $transcript = $data['transcript'] ?? null;       // array de turnos
        $audioBase64 = $data['full_audio'] ?? ($payload['full_audio'] ?? null);
        $hasAudio = is_string($audioBase64) && $audioBase64 !== '';

        // Variables de análisis de ElevenLabs en el shape que consume la UI del
        // detalle de llamada (Variables extraídas).
        $variablesAnalisis = $this->buildAnalysisVariables($analysis);

        // Log crudo de TODO evento recibido (payload sin el audio para no duplicarlo).
        $this->storeWebhookLog($agent, $payload, [
            'conversation_id' => $conversationId,
            'phone' => $phone,
            'event_type' => $payload['type'] ?? null,
            'status' => $data['status'] ?? null,
            'call_successful' => $analysis['call_successful'] ?? null,
            'duration_secs' => is_numeric($duration) ? (int) $duration : null,
            'cost' => is_numeric($cost) ? $cost : null,
            'has_audio' => $hasAudio,
        ]);

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
                    'variables_extraidas' => $variablesAnalisis ? json_encode($variablesAnalisis) : null,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($conversationId && $hasAudio) {
            try {
                RegistroAudioLlamada::create([
                    'conversation_id' => (string) $conversationId,
                    'audio' => $audioBase64,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
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
     * Normaliza el objeto `analysis` de ElevenLabs al shape que consume el modal
     * de detalle de llamada (call_summary_title, transcript_summary, call_successful,
     * data_collection_results_list, evaluation_criteria_results_list).
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    private function buildAnalysisVariables(array $analysis): ?array
    {
        if (! $analysis) {
            return null;
        }

        $dataCollection = [];
        $rawDataCollection = is_array($analysis['data_collection_results'] ?? null) ? $analysis['data_collection_results'] : [];
        foreach ($rawDataCollection as $key => $item) {
            $item = is_array($item) ? $item : ['value' => $item];
            $dataCollection[] = [
                'data_collection_id' => $item['data_collection_id'] ?? (string) $key,
                'value' => $item['value'] ?? null,
                'rationale' => $item['rationale'] ?? null,
            ];
        }

        $evaluation = [];
        $rawEvaluation = is_array($analysis['evaluation_criteria_results'] ?? null) ? $analysis['evaluation_criteria_results'] : [];
        foreach ($rawEvaluation as $key => $item) {
            $item = is_array($item) ? $item : ['result' => $item];
            $evaluation[] = [
                'criteria_id' => $item['criteria_id'] ?? ($item['criterion_id'] ?? (string) $key),
                'result' => $item['result'] ?? null,
                'rationale' => $item['rationale'] ?? null,
            ];
        }

        return [
            'call_summary_title' => $analysis['call_summary_title'] ?? null,
            'transcript_summary' => $analysis['transcript_summary'] ?? null,
            'call_successful' => $analysis['call_successful'] ?? null,
            'data_collection_results_list' => $dataCollection,
            'evaluation_criteria_results_list' => $evaluation,
        ];
    }

    /**
     * Persiste el log crudo del evento sin el audio (para no duplicar el base64).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    private function storeWebhookLog(Agent $agent, array $payload, array $meta): void
    {
        try {
            $clean = $payload;
            unset($clean['full_audio']);
            if (isset($clean['data']) && is_array($clean['data'])) {
                unset($clean['data']['full_audio']);
            }

            PostCallWebhookLog::create(array_merge($meta, [
                'agent_id' => $agent->id,
                'payload' => $clean,
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
