<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentFormResponse;
use App\Models\Client;
use App\Models\ContactQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContactQueueService
{
    public function computeNextRunAt(array $scheduleSnapshot, ?Carbon $from = null): ?Carbon
    {
        $timezone = $scheduleSnapshot['timezone'] ?? 'UTC';
        $times = $scheduleSnapshot['times'] ?? [];
        $daysOfWeek = $scheduleSnapshot['days_of_week'] ?? [];
        $excludedDates = $scheduleSnapshot['excluded_dates'] ?? [];

        if (empty($times) || empty($daysOfWeek)) {
            return null;
        }

        $tz = new \DateTimeZone($timezone);
        $from = $from ? Carbon::parse($from->format('Y-m-d H:i:s'), $tz) : Carbon::now($tz);
        $cursor = $from->copy()->startOfMinute();

        for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
            $day = $cursor->copy()->addDays($dayOffset);
            $dayNum = (int) $day->format('w');
            if (! in_array($dayNum, $daysOfWeek, true)) {
                continue;
            }
            $dateStr = $day->format('Y-m-d');
            if (in_array($dateStr, $excludedDates, true)) {
                continue;
            }
            foreach ($times as $timeStr) {
                if (! preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                    continue;
                }
                $candidate = Carbon::parse($dateStr.' '.$timeStr.':00', $tz);
                if ($candidate->gt($from)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function buildScheduleSnapshotFromConfig(array $scheduleConfig, string $timezone = 'America/Bogota'): array
    {
        $allRules = [];
        foreach ($scheduleConfig['execution_rules'] ?? [] as $rule) {
            if (! empty($rule['field']) && ! empty($rule['operator'])) {
                $r = [
                    'field' => $rule['field'],
                    'operator' => $rule['operator'],
                    'value' => $rule['value'] ?? null,
                ];
                $delayDays = isset($rule['delay_days']) ? (int) $rule['delay_days'] : 0;
                if ($delayDays > 0) {
                    $r['delay_days'] = $delayDays;
                    $r['delay_from'] = $rule['delay_from'] ?? 'loaded_at';
                }
                $allRules[] = $r;
            }
        }

        $campaignSlots = $scheduleConfig['campaign_slots'] ?? [];
        $times = [];
        $executionRulesByTime = [];
        $idPlantillaByTime = [];

        foreach ($campaignSlots as $slot) {
            $timeStr = is_array($slot) ? ($slot['time'] ?? null) : $slot;
            if (! $timeStr || ! preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                continue;
            }
            $times[] = $timeStr;
            $ruleIds = is_array($slot) ? ($slot['rule_ids'] ?? []) : [];
            $rulesForSlot = [];
            foreach ((array) $ruleIds as $idx) {
                if (isset($allRules[$idx])) {
                    $rulesForSlot[] = $allRules[$idx];
                }
            }
            $executionRulesByTime[$timeStr] = $rulesForSlot;
            $idPlantilla = is_array($slot) ? ($slot['id_plantilla'] ?? null) : null;
            if ($idPlantilla) {
                $idPlantillaByTime[$timeStr] = $idPlantilla;
            }
        }

        if (empty($times) && ! empty($scheduleConfig['campaign_times'] ?? [])) {
            $times = $scheduleConfig['campaign_times'];
            foreach ($times as $t) {
                $executionRulesByTime[$t] = $allRules;
            }
        }

        $daysOfWeek = [];
        foreach ($scheduleConfig['days_of_week'] ?? [] as $d) {
            $day = is_array($d) ? ($d['day'] ?? null) : $d;
            if ($day !== null) {
                $daysOfWeek[] = (int) $day;
            }
        }
        $excludedDates = array_values(array_filter($scheduleConfig['excluded_dates'] ?? []));

        return [
            'times' => array_values(array_unique($times)),
            'days_of_week' => array_unique($daysOfWeek),
            'excluded_dates' => $excludedDates,
            'timezone' => $timezone,
            'execution_rules_by_time' => $executionRulesByTime,
            'id_plantilla_by_time' => $idPlantillaByTime,
        ];
    }

    public function createQueue(Agent $agent, string $type, array $clientIds, bool $immediate = false, ?string $idPlantilla = null, ?Carbon $scheduledAt = null, ?array $clientSelectionRules = null, ?bool $isRecurring = null, bool $fromCallback = false): ContactQueue
    {
        $config = $type === ContactQueue::TYPE_CALL
            ? $agent->callConfig
            : $agent->messageConfig;

        $scheduleConfig = $config?->schedule_config ?? [];
        $timezone = $scheduleConfig['campaign_timezone'] ?? 'America/Bogota';

        if ($fromCallback) {
            $scheduleSnapshot = [
                'times' => [],
                'days_of_week' => [],
                'excluded_dates' => [],
                'timezone' => $timezone,
                'execution_rules_by_time' => [],
                'id_plantilla_by_time' => [],
                'from_callback' => true,
            ];
            if ($type === ContactQueue::TYPE_WHATSAPP) {
                $scheduleSnapshot['callback_plantilla_id'] = $idPlantilla ?? $agent->messageConfig?->default_plantilla_id;
            }
        } else {
            $scheduleSnapshot = $this->buildScheduleSnapshotFromConfig($scheduleConfig, $timezone);
        }

        if ($immediate && $type === ContactQueue::TYPE_WHATSAPP && $idPlantilla !== null) {
            $scheduleSnapshot['immediate_plantilla'] = $idPlantilla;
        }

        $nextRunAt = null;
        if ($scheduledAt !== null) {
            $nextRunAt = $scheduledAt;
        } elseif (! $immediate && ! empty($scheduleSnapshot['times']) && ! empty($scheduleSnapshot['days_of_week'])) {
            $nextRunAt = $this->computeNextRunAt($scheduleSnapshot);
        } elseif ($immediate) {
            $nextRunAt = now();
        }

        if ($isRecurring !== null) {
            $scheduleSnapshot['is_recurring'] = $isRecurring;
        }

        $data = [
            'agent_id' => $agent->id,
            'type' => $type,
            'client_ids' => $clientIds,
            'status' => ContactQueue::STATUS_PENDING,
            'schedule_snapshot' => $scheduleSnapshot,
            'next_run_at' => $nextRunAt,
        ];
        if ($clientSelectionRules !== null && ! empty($clientSelectionRules)) {
            $data['client_selection_rules'] = $clientSelectionRules;
        }

        return ContactQueue::create($data);
    }

    public function sendToWebhook(Agent $agent, Client $client, string $type, ?string $idPlantilla = null): bool
    {
        if ($type === ContactQueue::TYPE_CALL) {
            return $this->sendCallWebhook($agent, $client);
        }

        return $this->sendWhatsappWebhook($agent, $client, $idPlantilla);
    }

    /**
     * Envía una llamada al webhook incluyendo variables dinámicas extra
     * (ej. queue_call_id para emparejar el post-call). Usado por la cola por lotes.
     *
     * @param  array<string, mixed>  $extra
     */
    public function sendCall(Agent $agent, Client $client, array $extra = []): bool
    {
        return $this->sendCallWebhook($agent, $client, $extra);
    }

    protected function sendCallWebhook(Agent $agent, Client $client, array $extra = []): bool
    {
        $agent->loadMissing(['callConfig', 'clientSourceEndpoints']);
        $webhookUrl = $agent->callConfig?->webhook_url;
        if (! $webhookUrl) {
            return false;
        }

        $payload = $this->buildCallPayload($agent, $client, $extra);

        Log::info('[ContactQueueService] Llamada webhook (call)', [
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'webhook_url' => $webhookUrl,
        ]);
        $response = Http::timeout(30)->post($webhookUrl, $payload);
        $ok = $response->successful();
        if (! $ok) {
            Log::warning('[ContactQueueService] Webhook call falló', [
                'agent_id' => $agent->id,
                'client_id' => $client->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $ok;
    }

    /**
     * Payload POST de una llamada: agente + phone_number de ElevenLabs, datos del
     * cliente, variables dinámicas (campos + custom_fields) y datos de precarga.
     * Lo usan el botón "Llamar" (individual) y la cola de llamadas por lotes.
     *
     * @param  array<string, mixed>  $extra  Variables dinámicas extra (ej. queue_call_id).
     * @return array<string, mixed>
     */
    public function buildCallPayload(Agent $agent, Client $client, array $extra = []): array
    {
        $agent->loadMissing(['callConfig', 'clientSourceEndpoints']);
        $callConfig = $agent->callConfig;

        $clientPayload = [
            'id' => $client->id,
            'name' => $client->name,
            'lastname' => $client->lastname,
            'email' => $client->email,
            'phone' => $client->phone,
            'document_type' => $client->document_type,
            'document' => $client->document,
            'custom_fields' => $client->custom_fields ?? [],
        ];

        $preloadEndpoints = [];
        $preloadData = [];
        foreach ($agent->clientSourceEndpoints ?? [] as $ep) {
            $preloadEndpoints[] = [
                'name' => $ep->name,
                'url' => $ep->url,
                'headers' => $ep->headers ?? [],
            ];
            try {
                $url = $ep->url;
                $query = array_filter(['email' => $client->email, 'phone' => $client->phone]);
                if (! empty($query)) {
                    $url .= (str_contains($ep->url, '?') ? '&' : '?').http_build_query($query);
                }
                $response = Http::withHeaders($ep->headers ?? [])->timeout(10)->get($url);
                $preloadData[$ep->name] = $response->successful() ? $response->json() : null;
            } catch (\Throwable) {
                $preloadData[$ep->name] = null;
            }
        }

        return [
            'agent_id' => $callConfig?->elevenlabs_agent_id,
            'phone_number_id' => $callConfig?->elevenlabs_phone_number_id,
            'to_number' => $client->phone,
            'client' => $clientPayload,
            'dynamic_variables' => $this->buildDynamicVariables($client, $extra),
            'preload_endpoints' => $preloadEndpoints,
            'preload_data' => $preloadData,
        ];
    }

    /**
     * Variables dinámicas para ElevenLabs: campos del cliente + custom_fields (planos).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function buildDynamicVariables(Client $client, array $extra = []): array
    {
        $vars = array_filter([
            'client_id' => (string) $client->id,
            'name' => $client->name,
            'lastname' => $client->lastname,
            'full_name' => trim((string) $client->name.' '.(string) $client->lastname),
            'phone' => $client->phone,
            'email' => $client->email,
            'document_type' => $client->document_type,
            'document' => $client->document,
        ], fn ($v) => $v !== null && $v !== '');

        foreach (($client->custom_fields ?? []) as $key => $value) {
            if (is_scalar($value)) {
                $vars[(string) $key] = (string) $value;
            } elseif ($value !== null) {
                $vars[(string) $key] = json_encode($value);
            }
        }

        foreach ($extra as $k => $v) {
            $vars[$k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        return $vars;
    }

    protected function sendWhatsappWebhook(Agent $agent, Client $client, ?string $idPlantilla = null): bool
    {
        if (! $client->phone) {
            return false;
        }
        $agent->load('messageConfig');
        $webhookUrl = $agent->messageConfig?->webhook_url;
        if (! $webhookUrl) {
            return false;
        }

        $payload = [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'lastname' => $client->lastname,
                'email' => $client->email,
                'phone' => $client->phone,
                'document_type' => $client->document_type,
                'document' => $client->document,
                'custom_fields' => $client->custom_fields ?? [],
                'form_url' => AgentFormResponse::urlForClient($agent, $client),
            ],
        ];

        if ($idPlantilla !== null && $idPlantilla !== '') {
            $payload['id_plantilla'] = $idPlantilla;

            $variables = $agent->messageConfig?->resolvePlantillaVariables($idPlantilla, $client, $agent) ?? [];
            if ($variables !== []) {
                $payload['variables'] = $variables;
            }
        }

        Log::info('[ContactQueueService] Llamada webhook (whatsapp)', [
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'webhook_url' => $webhookUrl,
        ]);
        $response = Http::timeout(30)->post($webhookUrl, $payload);
        $ok = $response->successful();
        if (! $ok) {
            Log::warning('[ContactQueueService] Webhook whatsapp falló', [
                'agent_id' => $agent->id,
                'client_id' => $client->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $ok;
    }

    /**
     * Filtra clientes según las reglas de ejecución del schedule_snapshot.
     * Si hay execution_rules_by_time, usa las reglas del slot correspondiente a $runTime (ej: "09:00").
     * Si no, usa execution_rules (retrocompatibilidad).
     */
    public function filterClientsByExecutionRules(\Illuminate\Support\Collection $clients, array $scheduleSnapshot, ?string $runTime = null): \Illuminate\Support\Collection
    {
        $rulesByTime = $scheduleSnapshot['execution_rules_by_time'] ?? [];
        $rules = [];
        if (! empty($rulesByTime) && $runTime) {
            $rules = $rulesByTime[$runTime] ?? [];
        }
        if (empty($rules)) {
            $rules = $scheduleSnapshot['execution_rules'] ?? [];
        }
        if (empty($rules)) {
            return $clients;
        }

        return $clients->filter(function (Client $client) use ($rules) {
            foreach ($rules as $rule) {
                $field = $rule['field'] ?? '';
                $operator = $rule['operator'] ?? 'equals';
                $expected = $rule['value'] ?? null;

                $actual = $this->getClientFieldValue($client, $field);

                if (! $this->clientMatchesRule($actual, $operator, $expected)) {
                    return false;
                }

                if (! $this->clientMatchesDelayRule($client, $rule)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Verifica si el cliente cumple la regla de retraso (ej: contactar a los 3 días).
     * delay_from: loaded_at, updated_at, created_at o custom_fields.fecha_xxx
     */
    protected function clientMatchesDelayRule(Client $client, array $rule): bool
    {
        $delayDays = (int) ($rule['delay_days'] ?? 0);
        if ($delayDays <= 0) {
            return true;
        }

        $delayFrom = $rule['delay_from'] ?? 'loaded_at';
        $dateValue = $this->getClientDateValue($client, $delayFrom);
        if (! $dateValue) {
            return false;
        }

        $refDate = $dateValue instanceof Carbon ? $dateValue : Carbon::parse($dateValue);
        $minDate = now()->subDays($delayDays)->startOfDay();

        return $refDate->lte($minDate);
    }

    protected function getClientDateValue(Client $client, string $field): ?Carbon
    {
        if ($field === 'last_contacted_at') {
            $val = $client->getAttribute('last_contacted_at')
                ?? $client->contactLogs()->max('contacted_at');

            return $val ? Carbon::parse($val) : null;
        }
        if (in_array($field, ['loaded_at', 'updated_at', 'created_at'], true)) {
            $val = $client->getAttribute($field);

            return $val ? Carbon::parse($val) : null;
        }
        if (str_starts_with($field, 'custom_fields.')) {
            $key = substr($field, strlen('custom_fields.'));
            $cf = $client->custom_fields ?? [];
            $val = $cf[$key] ?? null;
            if (! $val) {
                return null;
            }

            try {
                return Carbon::parse($val);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function getClientFieldValue(Client $client, string $field): mixed
    {
        if ($field === 'contact_logs_count') {
            return $client->getAttribute('contact_logs_count') ?? $client->contactLogs()->count();
        }
        if (str_starts_with($field, 'custom_fields.')) {
            $key = substr($field, strlen('custom_fields.'));
            $cf = $client->custom_fields ?? [];

            return $cf[$key] ?? null;
        }

        return $client->getAttribute($field);
    }

    protected function clientMatchesRule(mixed $actual, string $operator, mixed $expected): bool
    {
        $actualStr = is_string($actual) ? trim($actual) : $actual;
        if (is_array($expected)) {
            $expected = array_map(fn ($v) => is_string($v) ? trim((string) $v) : $v, $expected);
        } else {
            $expected = is_string($expected) ? trim((string) $expected) : $expected;
        }

        $actualNum = is_numeric($actualStr) ? (float) $actualStr : null;
        $expectedNum = is_numeric($expected) ? (float) $expected : null;

        return match ($operator) {
            'equals' => (string) $actualStr === (string) $expected,
            'not_equals' => (string) $actualStr !== (string) $expected,
            'in' => is_array($expected) && in_array($actualStr, $expected, false),
            'not_in' => is_array($expected) && ! in_array($actualStr, $expected, false),
            'gte' => $actualNum !== null && $expectedNum !== null && $actualNum >= $expectedNum,
            'lte' => $actualNum !== null && $expectedNum !== null && $actualNum <= $expectedNum,
            'gt' => $actualNum !== null && $expectedNum !== null && $actualNum > $expectedNum,
            'lt' => $actualNum !== null && $expectedNum !== null && $actualNum < $expectedNum,
            default => false,
        };
    }

    public function computeNextRunForRecurring(ContactQueue $queue): ?Carbon
    {
        if (! $queue->isRecurring()) {
            return null;
        }
        $from = $queue->last_run_at ?? $queue->next_run_at ?? now();
        $from = Carbon::parse($from)->addMinute();

        return $this->computeNextRunAt($queue->schedule_snapshot ?? [], $from);
    }
}
