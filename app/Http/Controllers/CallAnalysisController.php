<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\CallAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dispara el análisis de IA de una llamada desde el panel (el modal de
 * transcripción ya tiene la transcripción cargada y la envía aquí).
 */
class CallAnalysisController extends Controller
{
    public function analyze(Request $request, Agent $agent, CallAnalysisService $service): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|string|max:255',
            'transcript' => 'required|string',
            'phone' => 'nullable|string|max:50',
        ]);

        if (! $service->isConfigured()) {
            return response()->json([
                'error' => 'El proveedor de IA no está configurado. Define OPENAI_API_KEY en el entorno.',
            ], 422);
        }

        try {
            $analysis = $service->analyze(
                $agent,
                $validated['conversation_id'],
                $validated['transcript'],
                $validated['phone'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo analizar la llamada: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'analysis' => [
                'summary' => $analysis->summary,
                'sentiment' => $analysis->sentiment,
                'motivo' => $analysis->motivo,
                'resultado' => $analysis->resultado,
                'requires_alert' => $analysis->requires_alert,
                'category_slug' => $analysis->category_slug,
                'alerta_llamada_id' => $analysis->alerta_llamada_id,
                'data' => $analysis->data,
            ],
        ]);
    }
}
