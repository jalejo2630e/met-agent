<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentReportWidget;
use Illuminate\Support\Collection;

/**
 * Calcula los datasets de los widgets de reporte personalizados de un agente.
 *
 * Cada widget define una "regla" (tramos numéricos, valores discretos, avance por
 * cliente o métricas de llamadas) sobre un campo dinámico de los clientes
 * (clients.custom_fields) o sobre las llamadas de Supabase. El resultado son datos
 * listos para graficar en el front (labels + values + colores + detalle).
 */
class CustomReportBuilderService
{
    /** Paleta por defecto para segmentos sin color explícito. */
    private const PALETTE = [
        '#2e7d32', '#1976d2', '#f9a825', '#c62828', '#6a1b9a',
        '#00838f', '#ef6c00', '#5e35b1', '#43a047', '#3949ab',
    ];

    public function __construct(
        protected CallCountService $calls
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function build(Agent $agent, ?string $dateFrom, ?string $dateTo): array
    {
        $widgets = $agent->reportWidgets()->get();
        if ($widgets->isEmpty()) {
            return [];
        }

        [$start, $end] = $this->range($dateFrom, $dateTo);
        $clients = $this->loadClients($agent, $start, $end);

        // Conteo de llamadas por teléfono (solo si algún widget lo necesita).
        $callsByPhone = null;
        if ($widgets->contains(fn (AgentReportWidget $w) => $w->metric === AgentReportWidget::METRIC_CALLS)) {
            $phones = $clients->pluck('phone')->filter(fn ($p) => filled($p))->all();
            $callsByPhone = $this->calls->countsByPhone($phones, $start, $end);
        }

        return $widgets->map(function (AgentReportWidget $widget) use ($clients, $callsByPhone) {
            return [
                'id' => $widget->id,
                'title' => $widget->title,
                'metric' => $widget->metric,
                'source' => $widget->source,
                'field_name' => $widget->field_name,
                'chart_type' => $widget->chart_type,
                'config' => $widget->config ?? [],
                'order' => $widget->order,
                'data' => $this->computeWidget($widget, $clients, $callsByPhone),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, \App\Models\Client>  $clients
     * @param  array<string, int>|null  $callsByPhone
     * @return array<string, mixed>
     */
    protected function computeWidget(AgentReportWidget $widget, Collection $clients, ?array $callsByPhone): array
    {
        return match ($widget->metric) {
            AgentReportWidget::METRIC_RANGE_BUCKETS => $this->rangeBuckets($widget, $clients),
            AgentReportWidget::METRIC_CLIENT_PROGRESS => $this->clientProgress($widget, $clients),
            AgentReportWidget::METRIC_TOPIC_PROGRESS => $this->topicProgress($widget, $clients),
            AgentReportWidget::METRIC_VALUE_COUNTS => $this->valueCounts($widget, $clients),
            AgentReportWidget::METRIC_CALLS => $this->callsMetric($widget, $clients, $callsByPhone ?? []),
            default => ['labels' => [], 'values' => [], 'colors' => []],
        };
    }

    /**
     * Avance por temas usando campos "compañeros" (ej. fatiga y emociones, 0..max).
     *
     * Reglas (confirmadas con el usuario):
     * - Cada tema tiene un campo con valor 0..max. valor >= max ⇒ tema completado.
     * - El tema ACTUAL del cliente es el que NO está completado (si un campo llegó al
     *   máximo, el cliente ya pasó a otro tema). El STEP no define el tema.
     * - Si varios temas están sin completar, se prefiere el que está en progreso
     *   (0 < valor < max); si ninguno está en progreso, el primero pendiente.
     * - % de avance del cliente = valor / max del tema actual.
     *
     * @param  Collection<int, \App\Models\Client>  $clients
     * @return array<string, mixed>
     */
    protected function topicProgress(AgentReportWidget $widget, Collection $clients): array
    {
        $topics = $this->topics($widget);
        if ($topics === []) {
            return [
                'distribution' => ['labels' => [], 'values' => [], 'colors' => []],
                'rows' => [], 'total_rows' => 0, 'topic_summary' => [],
                'completed_count' => 0, 'not_started_count' => 0, 'total' => $clients->count(),
            ];
        }

        $rows = [];
        $topicCounts = [];   // label => nº clientes en ese tema (en progreso)
        $topicPctSum = [];   // label => suma de % (para promedio)
        foreach ($topics as $t) {
            $topicCounts[$t['label']] = 0;
            $topicPctSum[$t['label']] = 0.0;
        }
        $completedCount = 0;
        $notStartedCount = 0;

        foreach ($clients as $client) {
            $eval = $this->evaluateClientTopics($client->custom_fields ?? [], $topics);
            $status = $eval['status'];
            $label = $eval['current_topic'];
            $pct = $eval['current_pct'];

            if ($status === 'completed') {
                $completedCount++;
            } elseif ($status === 'not_started') {
                $notStartedCount++;
            } elseif ($label !== null) {
                $topicCounts[$label]++;
                $topicPctSum[$label] += ($pct ?? 0);
            }

            $rows[] = [
                'id' => $client->id,
                'name' => trim($client->name.' '.$client->lastname),
                'topic' => $label,
                'topic_pct' => $pct,
                'status' => $status,
            ];
        }

        // Orden: en progreso primero (por % desc), luego completados, luego sin iniciar.
        $rank = ['in_progress' => 0, 'completed' => 1, 'not_started' => 2];
        usort($rows, function ($a, $b) use ($rank) {
            if ($rank[$a['status']] !== $rank[$b['status']]) {
                return $rank[$a['status']] <=> $rank[$b['status']];
            }

            return ($b['topic_pct'] ?? 0) <=> ($a['topic_pct'] ?? 0);
        });

        $topicSummary = [];
        foreach ($topics as $i => $t) {
            $label = $t['label'];
            $topicSummary[] = [
                'label' => $label,
                'color' => $t['color'] ?: self::PALETTE[$i % count(self::PALETTE)],
                'clients' => $topicCounts[$label],
                'avg_pct' => $topicCounts[$label] > 0 ? round($topicPctSum[$label] / $topicCounts[$label], 1) : 0.0,
            ];
        }

        // Distribución para doughnut/barras (temas + completado + sin iniciar).
        $distLabels = array_map(fn ($t) => $t['label'], $topics);
        $distValues = array_map(fn ($t) => $topicCounts[$t['label']], $topics);
        $distColors = array_map(fn ($t, $i) => $t['color'] ?: self::PALETTE[$i % count(self::PALETTE)], $topics, array_keys($topics));
        if ($completedCount > 0) {
            $distLabels[] = 'Completado';
            $distValues[] = $completedCount;
            $distColors[] = '#2e7d32';
        }
        if ($notStartedCount > 0) {
            $distLabels[] = 'Sin iniciar';
            $distValues[] = $notStartedCount;
            $distColors[] = '#9e9e9e';
        }

        return [
            'distribution' => ['labels' => $distLabels, 'values' => $distValues, 'colors' => $distColors],
            'rows' => array_slice($rows, 0, 100),
            'total_rows' => count($rows),
            'topic_summary' => $topicSummary,
            'completed_count' => $completedCount,
            'not_started_count' => $notStartedCount,
            'total' => $clients->count(),
        ];
    }

    /**
     * Conteo de clientes por tramo numérico. Sirve tanto para doughnut/barras
     * (distribución) como para embudo (funnel), donde el front usa el orden y % de caída.
     *
     * @param  Collection<int, \App\Models\Client>  $clients
     * @return array<string, mixed>
     */
    protected function rangeBuckets(AgentReportWidget $widget, Collection $clients): array
    {
        $field = (string) $widget->field_name;
        $ranges = $this->ranges($widget);

        $counts = array_fill(0, count($ranges), 0);
        $noData = 0;
        $outOfRange = 0;

        foreach ($clients as $client) {
            $value = $this->numericValue($client->custom_fields ?? [], $field);
            if ($value === null) {
                $noData++;

                continue;
            }
            $matched = false;
            foreach ($ranges as $i => $r) {
                if ($value >= $r['from'] && $value <= $r['to']) {
                    $counts[$i]++;
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $outOfRange++;
            }
        }

        $labels = array_map(fn ($r) => $r['label'], $ranges);
        $colors = array_map(fn ($r, $i) => $r['color'] ?: self::PALETTE[$i % count(self::PALETTE)], $ranges, array_keys($ranges));

        return [
            'labels' => $labels,
            'values' => $counts,
            'colors' => $colors,
            'ranges' => $ranges,
            'no_data' => $noData,
            'out_of_range' => $outOfRange,
            'total' => $clients->count(),
            'total_in_ranges' => array_sum($counts),
        ];
    }

    /**
     * Avance porcentual por cliente respecto a un máximo, con el tema (tramo) en el que va.
     *
     * @param  Collection<int, \App\Models\Client>  $clients
     * @return array<string, mixed>
     */
    protected function clientProgress(AgentReportWidget $widget, Collection $clients): array
    {
        $field = (string) $widget->field_name;
        $ranges = $this->ranges($widget);
        $max = (float) ($widget->config['max'] ?? 0);
        if ($max <= 0) {
            // Si no se define máximo, usar el tope del último tramo.
            $max = $ranges ? (float) end($ranges)['to'] : 100.0;
        }

        $rows = [];
        $topicSum = [];   // label => suma de %
        $topicCount = []; // label => nº clientes
        $overallSum = 0.0;
        $withValue = 0;

        foreach ($clients as $client) {
            $value = $this->numericValue($client->custom_fields ?? [], $field);
            if ($value === null) {
                continue;
            }
            $withValue++;
            $overallPct = $max > 0 ? round(min(100, ($value / $max) * 100), 1) : 0.0;
            $overallSum += $overallPct;

            $topic = null;
            $topicPct = null;
            foreach ($ranges as $r) {
                if ($value >= $r['from'] && $value <= $r['to']) {
                    $topic = $r['label'];
                    $span = ($r['to'] - $r['from'] + 1);
                    $topicPct = $span > 0 ? round((($value - $r['from'] + 1) / $span) * 100, 1) : 100.0;
                    break;
                }
            }
            if ($topic !== null) {
                $topicSum[$topic] = ($topicSum[$topic] ?? 0) + $topicPct;
                $topicCount[$topic] = ($topicCount[$topic] ?? 0) + 1;
            }

            $rows[] = [
                'id' => $client->id,
                'name' => trim($client->name.' '.$client->lastname),
                'value' => $value,
                'overall_pct' => $overallPct,
                'topic' => $topic,
                'topic_pct' => $topicPct,
            ];
        }

        usort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);

        $topicAverages = [];
        foreach ($ranges as $i => $r) {
            $label = $r['label'];
            $topicAverages[] = [
                'label' => $label,
                'avg_pct' => isset($topicCount[$label]) && $topicCount[$label] > 0
                    ? round($topicSum[$label] / $topicCount[$label], 1)
                    : 0.0,
                'clients' => $topicCount[$label] ?? 0,
                'color' => $r['color'] ?: self::PALETTE[$i % count(self::PALETTE)],
            ];
        }

        return [
            'rows' => array_slice($rows, 0, 100),
            'total_rows' => count($rows),
            'with_value' => $withValue,
            'overall_avg_pct' => $withValue > 0 ? round($overallSum / $withValue, 1) : 0.0,
            'max' => $max,
            'topic_averages' => $topicAverages,
        ];
    }

    /**
     * Conteo de clientes por valor discreto de un campo (ej. calificación 1..5).
     *
     * @param  Collection<int, \App\Models\Client>  $clients
     * @return array<string, mixed>
     */
    protected function valueCounts(AgentReportWidget $widget, Collection $clients): array
    {
        $field = (string) $widget->field_name;
        $configValues = $widget->config['values'] ?? [];

        $rawCounts = []; // valor_string => count
        $numericValues = [];
        $numericSum = 0.0;
        $numericCount = 0;

        foreach ($clients as $client) {
            $cf = $client->custom_fields ?? [];
            if (! array_key_exists($field, $cf)) {
                continue;
            }
            $raw = $cf[$field];
            if ($raw === null || $raw === '') {
                continue;
            }
            $key = is_bool($raw) ? ($raw ? 'true' : 'false') : trim((string) $raw);
            if ($key === '') {
                continue;
            }
            $rawCounts[$key] = ($rawCounts[$key] ?? 0) + 1;

            $num = $this->toNumber($raw);
            if ($num !== null) {
                $numericSum += $num;
                $numericCount++;
                $numericValues[$key] = $num;
            }
        }

        // Definir el orden y etiquetas de las columnas.
        if (! empty($configValues)) {
            $labels = [];
            $values = [];
            $colors = [];
            foreach ($configValues as $i => $v) {
                $val = trim((string) ($v['value'] ?? ''));
                $labels[] = $v['label'] ?? $val;
                $values[] = $rawCounts[$val] ?? 0;
                $colors[] = ($v['color'] ?? '') ?: self::PALETTE[$i % count(self::PALETTE)];
            }
        } else {
            // Auto: ordenar numéricamente si todos son numéricos, si no alfabéticamente.
            $keys = array_keys($rawCounts);
            $allNumeric = $keys !== [] && count($numericValues) === count($keys);
            if ($allNumeric) {
                usort($keys, fn ($a, $b) => $numericValues[$a] <=> $numericValues[$b]);
            } else {
                sort($keys);
            }
            $labels = $keys;
            $values = array_map(fn ($k) => $rawCounts[$k], $keys);
            $colors = array_map(fn ($k, $i) => self::PALETTE[$i % count(self::PALETTE)], $keys, array_keys($keys));
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
            'total' => array_sum($values),
            'average' => $numericCount > 0 ? round($numericSum / $numericCount, 2) : null,
        ];
    }

    /**
     * Métricas de llamadas: total, promedio por cliente y distribución.
     *
     * @param  Collection<int, \App\Models\Client>  $clients
     * @param  array<string, int>  $callsByPhone
     * @return array<string, mixed>
     */
    protected function callsMetric(AgentReportWidget $widget, Collection $clients, array $callsByPhone): array
    {
        $metrics = $widget->config['calls_metrics'] ?? ['total', 'avg', 'distribution'];

        $clientsWithPhone = 0;
        $totalCalls = 0;
        $buckets = ['0' => 0, '1' => 0, '2' => 0, '3-5' => 0, '6+' => 0];

        foreach ($clients as $client) {
            $digits = preg_replace('/\D/', '', (string) $client->phone);
            if ($digits === '') {
                continue;
            }
            $clientsWithPhone++;
            $c = $callsByPhone[$digits] ?? 0;
            $totalCalls += $c;

            if ($c <= 0) {
                $buckets['0']++;
            } elseif ($c === 1) {
                $buckets['1']++;
            } elseif ($c === 2) {
                $buckets['2']++;
            } elseif ($c <= 5) {
                $buckets['3-5']++;
            } else {
                $buckets['6+']++;
            }
        }

        return [
            'metrics' => array_values($metrics),
            'total_calls' => $totalCalls,
            'clients_with_phone' => $clientsWithPhone,
            'avg_per_client' => $clientsWithPhone > 0 ? round($totalCalls / $clientsWithPhone, 2) : 0.0,
            'distribution' => [
                'labels' => array_keys($buckets),
                'values' => array_values($buckets),
                'colors' => array_slice(self::PALETTE, 0, count($buckets)),
            ],
        ];
    }

    /**
     * Normaliza y valida los tramos definidos por el usuario.
     *
     * @return list<array{from: float, to: float, label: string, color: string}>
     */
    protected function ranges(AgentReportWidget $widget): array
    {
        $ranges = [];
        foreach (($widget->config['ranges'] ?? []) as $r) {
            $from = $this->toNumber($r['from'] ?? null);
            $to = $this->toNumber($r['to'] ?? null);
            if ($from === null || $to === null) {
                continue;
            }
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $ranges[] = [
                'from' => $from,
                'to' => $to,
                'label' => (string) ($r['label'] ?? "{$from}-{$to}"),
                'color' => (string) ($r['color'] ?? ''),
            ];
        }

        return $ranges;
    }

    protected function topics(AgentReportWidget $widget): array
    {
        return $this->normalizeTopics($widget->config['topics'] ?? []);
    }

    /**
     * Normaliza una lista de temas (campo compañero + máximo por tema).
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return list<array{label: string, field: string, max: float, color: string}>
     */
    public function normalizeTopics(array $raw): array
    {
        $topics = [];
        foreach ($raw as $i => $t) {
            $field = trim((string) ($t['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $max = $this->toNumber($t['max'] ?? null);
            if ($max === null || $max <= 0) {
                $max = 3.0;
            }
            $topics[] = [
                'label' => (string) ($t['label'] ?? $field),
                'field' => $field,
                'max' => $max,
                'color' => ((string) ($t['color'] ?? '')) ?: self::PALETTE[$i % count(self::PALETTE)],
            ];
        }

        return $topics;
    }

    /**
     * Evalúa el avance por temas de UN cliente a partir de sus custom_fields.
     * Fuente única de verdad para el reporte y para el detalle del cliente.
     *
     * @param  array<string, mixed>  $customFields
     * @param  list<array{label: string, field: string, max: float, color: string}>  $topics
     * @return array<string, mixed>
     */
    public function evaluateClientTopics(array $customFields, array $topics): array
    {
        $vals = array_map(fn ($t) => $this->numericValue($customFields, $t['field']) ?? 0.0, $topics);

        $allCompleted = true;
        $anyStarted = false;
        foreach ($topics as $i => $t) {
            if ($vals[$i] < $t['max']) {
                $allCompleted = false;
            }
            if ($vals[$i] > 0) {
                $anyStarted = true;
            }
        }

        $status = 'in_progress';
        $currentIdx = null;

        if ($allCompleted && $anyStarted) {
            $status = 'completed';
        } else {
            // Preferir un tema en progreso (0 < valor < max); si hay varios, el de mayor valor.
            $bestVal = -1.0;
            foreach ($topics as $i => $t) {
                if ($vals[$i] > 0 && $vals[$i] < $t['max'] && $vals[$i] > $bestVal) {
                    $currentIdx = $i;
                    $bestVal = $vals[$i];
                }
            }
            // Si ninguno está en progreso, el primer tema pendiente.
            if ($currentIdx === null) {
                foreach ($topics as $i => $t) {
                    if ($vals[$i] < $t['max']) {
                        $currentIdx = $i;
                        break;
                    }
                }
            }
            if (! $anyStarted) {
                $status = 'not_started';
            }
        }

        $currentLabel = $currentIdx !== null ? $topics[$currentIdx]['label'] : null;
        $currentPct = null;
        if ($currentIdx !== null) {
            $max = $topics[$currentIdx]['max'];
            $currentPct = $max > 0 ? round(min(100, ($vals[$currentIdx] / $max) * 100), 1) : 0.0;
        }

        $detail = [];
        foreach ($topics as $i => $t) {
            $detail[] = [
                'label' => $t['label'],
                'field' => $t['field'],
                'max' => $t['max'],
                'color' => $t['color'],
                'value' => $vals[$i],
                'completed' => $vals[$i] >= $t['max'],
                'pct' => $t['max'] > 0 ? round(min(100, ($vals[$i] / $t['max']) * 100), 1) : 0.0,
            ];
        }

        return [
            'topics' => $detail,
            'current_topic' => $status === 'completed' ? null : $currentLabel,
            'current_pct' => $status === 'completed' ? 100.0 : $currentPct,
            'status' => $status,
        ];
    }

    /**
     * Lee un campo de custom_fields y lo convierte a número, o null si no aplica.
     *
     * @param  array<string, mixed>  $customFields
     */
    protected function numericValue(array $customFields, string $field): ?float
    {
        if (! array_key_exists($field, $customFields)) {
            return null;
        }

        return $this->toNumber($customFields[$field]);
    }

    /** Coerción tolerante a número: acepta int/float/string ("12", "12,5", "12.0"). */
    protected function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $v = trim(str_replace(',', '.', $value));
        if ($v === '' || ! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function range(?string $dateFrom, ?string $dateTo): array
    {
        $start = $dateFrom ? ($dateFrom.' 00:00:00') : null;
        $end = $dateTo ? ($dateTo.' 23:59:59') : null;

        return [$start, $end];
    }

    /**
     * Carga los clientes del agente (con el filtro de fechas del reporte) una sola vez.
     *
     * @return Collection<int, \App\Models\Client>
     */
    protected function loadClients(Agent $agent, ?string $start, ?string $end): Collection
    {
        $query = $agent->clients();

        if ($start) {
            $query->where(function ($q) use ($start) {
                $q->whereHas('loadDates', fn ($q2) => $q2->where('loaded_at', '>=', $start))
                    ->orWhere(fn ($q2) => $q2->whereDoesntHave('loadDates')->where('created_at', '>=', $start));
            });
        }
        if ($end) {
            $query->where(function ($q) use ($end) {
                $q->whereHas('loadDates', fn ($q2) => $q2->where('loaded_at', '<=', $end))
                    ->orWhere(fn ($q2) => $q2->whereDoesntHave('loadDates')->where('created_at', '<=', $end));
            });
        }

        return $query->get(['id', 'name', 'lastname', 'phone', 'custom_fields', 'updated_at']);
    }
}
