<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Costos estimados de IA por agente: por tipo de operación (mensaje, audio,
 * imagen, extracción) y por modelo, agregados por mes.
 */
class AiCostController extends Controller
{
    private const KIND_LABELS = [
        'message' => 'Mensajes (texto)',
        'audio' => 'Audios (transcripción)',
        'image' => 'Imágenes (visión)',
        'extraction' => 'Extracción de variables',
    ];

    public function index(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('view', $agent);

        $month = (string) $request->query('month', '');
        try {
            $start = $month !== ''
                ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            $start = Carbon::now()->startOfMonth();
        }
        $end = (clone $start)->endOfMonth();

        $base = fn () => AiUsageLog::where('agent_id', $agent->id)->whereBetween('created_at', [$start, $end]);

        $byKind = $base()
            ->selectRaw('kind, count(*) as count, sum(cost) as cost, sum(prompt_tokens + completion_tokens) as tokens, sum(seconds) as seconds')
            ->groupBy('kind')->get()
            ->map(fn ($r) => [
                'kind' => $r->kind,
                'label' => self::KIND_LABELS[$r->kind] ?? $r->kind,
                'count' => (int) $r->count,
                'cost' => round((float) $r->cost, 4),
                'tokens' => (int) $r->tokens,
                'seconds' => round((float) $r->seconds, 1),
            ])->values();

        $byModel = $base()
            ->selectRaw('model, count(*) as count, sum(cost) as cost')
            ->groupBy('model')->orderByRaw('sum(cost) desc')->get()
            ->map(fn ($r) => [
                'model' => $r->model ?: '(desconocido)',
                'count' => (int) $r->count,
                'cost' => round((float) $r->cost, 4),
            ])->values();

        // Tendencia de los últimos 6 meses (costo total por mes).
        $trendStart = Carbon::now()->subMonths(5)->startOfMonth();
        $trend = AiUsageLog::where('agent_id', $agent->id)
            ->where('created_at', '>=', $trendStart)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, sum(cost) as cost")
            ->groupBy('ym')->orderBy('ym')->get()
            ->map(fn ($r) => ['month' => $r->ym, 'cost' => round((float) $r->cost, 4)]);

        return response()->json([
            'month' => $start->format('Y-m'),
            'total' => round((float) $base()->sum('cost'), 4),
            'by_kind' => $byKind,
            'by_model' => $byModel,
            'trend' => $trend,
        ]);
    }
}
