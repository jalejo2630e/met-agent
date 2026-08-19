<?php

namespace App\Services;

use App\Ai\Agents\CallAnalyst;
use App\Models\Agent;
use App\Models\AlertaCategoria;
use App\Models\AlertaLlamada;
use App\Models\CallAnalysis;
use App\Models\Client;
use App\Support\TranscriptNormalizer;

/**
 * Ejecuta el agente de IA CallAnalyst sobre la transcripción de una llamada,
 * persiste el resultado en call_analyses y, si procede, crea una AlertaLlamada
 * con la categoría detectada (reutilizando la lógica de Api\CallAlertApiController).
 */
class CallAnalysisService
{
    public function isConfigured(): bool
    {
        return (string) config('ai.providers.'.config('ai.default').'.key', '') !== '';
    }

    /**
     * Analiza una transcripción (texto plano o JSON [{role,message}]) y guarda el resultado.
     */
    public function analyze(Agent $agent, string $conversationId, string $transcript, ?string $phone = null): CallAnalysis
    {
        $text = TranscriptNormalizer::toText($transcript);

        if (trim($text) === '') {
            throw new \InvalidArgumentException('La transcripción está vacía.');
        }

        $categorySlugs = AlertaCategoria::query()->pluck('slug')->all();

        $response = (new CallAnalyst($categorySlugs, (string) $agent->name))
            ->prompt("Analiza la siguiente transcripción de llamada:\n\n".$text);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response, true) ?: [];

        $requiresAlert = (bool) ($data['requiere_alerta'] ?? false);
        $categorySlug = (string) ($data['categoria_alerta'] ?? 'ninguna');

        $analysis = CallAnalysis::updateOrCreate(
            ['agent_id' => $agent->id, 'conversation_id' => $conversationId],
            [
                'phone' => $phone,
                'summary' => $data['resumen'] ?? null,
                'sentiment' => $data['sentimiento'] ?? null,
                'category_slug' => $requiresAlert ? $categorySlug : null,
                'motivo' => $data['motivo_contacto'] ?? null,
                'resultado' => $data['resultado'] ?? null,
                'requires_alert' => $requiresAlert,
                'model' => isset($response->meta->model) ? $response->meta->model : null,
                'data' => $data,
            ],
        );

        if ($requiresAlert) {
            $this->createAlert($agent, $analysis, $categorySlug, $data, $phone);
        }

        return $analysis->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAlert(Agent $agent, CallAnalysis $analysis, string $categorySlug, array $data, ?string $phone): void
    {
        // Evitar duplicar la alerta si ya se generó para esta conversación.
        if ($analysis->alerta_llamada_id) {
            return;
        }

        $categoria = ($categorySlug !== '' && $categorySlug !== 'ninguna')
            ? AlertaCategoria::where('slug', $categorySlug)->first()
            : null;

        $phoneDigits = $phone ? preg_replace('/\D/', '', $phone) : '';
        $client = $phoneDigits
            ? Client::where('agent_id', $agent->id)->where('phone', $phoneDigits)->first()
            : null;

        $descripcion = trim((string) ($data['descripcion_alerta'] ?? ''));
        if ($descripcion === '') {
            $descripcion = (string) ($data['resumen'] ?? 'Alerta generada por análisis de IA.');
        }

        $alerta = AlertaLlamada::create([
            'client_id' => $client?->id,
            'agent_id' => $agent->id,
            'alerta_categoria_id' => $categoria?->id,
            'phone' => $phone,
            'descripcion' => $descripcion,
        ]);

        $analysis->update(['alerta_llamada_id' => $alerta->id]);
    }
}
