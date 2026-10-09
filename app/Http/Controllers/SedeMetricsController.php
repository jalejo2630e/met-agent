<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\TwilioMessage;
use App\Services\SedeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Métricas de interacción por sede (Bogotá / Chía) a partir de los mensajes de
 * Twilio. Los mensajes sin sede (número no configurado o anteriores al cambio)
 * se agrupan aparte como "Sin sede".
 */
class SedeMetricsController extends Controller
{
    public function index(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('view', $agent);

        $validated = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);

        $end = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::now()->endOfDay();
        $start = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : (clone $end)->subDays(29)->startOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $inRange = fn () => TwilioMessage::where('agent_id', $agent->id)->whereBetween('created_at', [$start, $end]);

        // Mensajes por sede y dirección.
        $totals = $inRange()
            ->selectRaw('sede, direction, count(*) as total')
            ->groupBy('sede', 'direction')->get();

        // Personas únicas que escribieron (mensajes entrantes) por sede.
        $unique = $inRange()->where('direction', 'inbound')
            ->selectRaw('sede, count(distinct from_number) as total')
            ->groupBy('sede')->pluck('total', 'sede');

        // Personas nuevas: su primer mensaje entrante en esa sede cae dentro del rango.
        $newPeople = TwilioMessage::where('agent_id', $agent->id)->where('direction', 'inbound')
            ->selectRaw('sede, from_number')
            ->groupBy('sede', 'from_number')
            ->havingRaw('min(created_at) between ? and ?', [$start, $end])
            ->get()->groupBy('sede')->map->count();

        // Serie diaria de mensajes entrantes por sede.
        $daily = $inRange()->where('direction', 'inbound')
            ->selectRaw('date(created_at) as day, sede, count(*) as total')
            ->groupBy('day', 'sede')->orderBy('day')->get();

        $keys = array_merge(array_keys(SedeResolver::labels()), [null]);
        $labels = SedeResolver::labels();

        $sedes = collect($keys)->map(function (?string $key) use ($totals, $unique, $newPeople, $labels) {
            $inbound = (int) $totals->first(fn ($r) => $r->sede === $key && $r->direction === 'inbound')?->total;
            $outbound = (int) $totals->first(fn ($r) => $r->sede === $key && $r->direction === 'outbound')?->total;
            $people = (int) ($unique[$key ?? ''] ?? 0);
            $new = (int) ($newPeople[$key ?? ''] ?? 0);

            return [
                'sede' => $key,
                'label' => $key === null ? 'Sin sede' : ($labels[$key] ?? $key),
                'inbound' => $inbound,
                'outbound' => $outbound,
                'unique_contacts' => $people,
                'new_contacts' => min($new, $people),
                'returning_contacts' => max(0, $people - min($new, $people)),
                'avg_inbound_per_contact' => $people > 0 ? round($inbound / $people, 1) : 0,
            ];
        })
            // "Sin sede" solo se muestra si tiene actividad.
            ->filter(fn (array $s) => $s['sede'] !== null || $s['inbound'] + $s['outbound'] > 0)
            ->values();

        return response()->json([
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'sedes' => $sedes,
            'daily' => $daily->map(fn ($r) => [
                'day' => (string) $r->day,
                'sede' => $r->sede,
                'total' => (int) $r->total,
            ])->values(),
        ]);
    }
}
