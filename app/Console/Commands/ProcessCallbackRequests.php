<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientCallbackRequest;
use App\Models\ContactQueue;
use App\Services\ContactQueueService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCallbackRequests extends Command
{
    protected $signature = 'callback-requests:process';

    protected $description = 'Procesa solicitudes de callback programadas para hoy y crea colas de contacto';

    public const CALLBACK_TIMEZONE = 'America/Bogota';

    public function handle(ContactQueueService $service): int
    {
        $today = now(self::CALLBACK_TIMEZONE)->toDateString();
        Log::info('[callback-requests:process] Iniciando procesamiento de callbacks para hoy (Colombia)', ['date' => $today]);

        $requests = ClientCallbackRequest::where('status', ClientCallbackRequest::STATUS_PENDING)
            ->whereDate('scheduled_date', $today)
            ->with(['client', 'agent'])
            ->orderBy('scheduled_time')
            ->get();

        if ($requests->isEmpty()) {
            Log::info('[callback-requests:process] No hay callbacks pendientes para hoy');
            return self::SUCCESS;
        }

        foreach ($requests->groupBy(fn ($r) => "{$r->agent_id}_{$r->channel}_{$r->scheduled_time}") as $group) {
            $first = $group->first();
            $agent = $first->agent;
            $channel = $first->channel;
            $type = $channel === ClientCallbackRequest::CHANNEL_WHATSAPP
                ? ContactQueue::TYPE_WHATSAPP
                : ContactQueue::TYPE_CALL;

            $config = $channel === ClientCallbackRequest::CHANNEL_WHATSAPP
                ? $agent->messageConfig
                : $agent->callConfig;

            if (! $config?->webhook_url) {
                $this->warn("Agente {$agent->id} no tiene webhook de {$channel} configurado. Callbacks omitidos.");
                continue;
            }

            $clientIds = $group->pluck('client_id')->unique()->values()->all();

            if ($type === ContactQueue::TYPE_WHATSAPP) {
                $clientIds = Client::whereIn('id', $clientIds)
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->pluck('id')
                    ->all();
            }

            if (empty($clientIds)) {
                continue;
            }

            $rawTime = $first->scheduled_time ?? '';
            $timeStr = (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $rawTime) ? substr($rawTime, 0, 5) : null) ?: '08:00';
            $tzName = self::CALLBACK_TIMEZONE;
            $tz = new \DateTimeZone($tzName);
            $scheduledAt = Carbon::parse(
                $first->scheduled_date->format('Y-m-d').' '.$timeStr.':00',
                $tz
            );
            $scheduledAtUtc = $scheduledAt->copy()->utc();

            Log::info('[callback-requests:process] Programando cola', [
                'scheduled_time_raw' => $rawTime,
                'scheduled_time_parsed' => $timeStr,
                'timezone' => $tzName,
                'scheduled_at_local' => $scheduledAt->format('Y-m-d H:i:s'),
                'scheduled_at_utc' => $scheduledAtUtc->format('Y-m-d H:i:s'),
            ]);

            $idPlantilla = ($type === ContactQueue::TYPE_WHATSAPP)
                ? ($agent->messageConfig?->default_plantilla_id ?? null)
                : null;
            $queue = $service->createQueue($agent, $type, $clientIds, false, $idPlantilla, $scheduledAtUtc, null, null, true);

            foreach ($group as $req) {
                if (in_array($req->client_id, $clientIds, true)) {
                    $req->update(['status' => ClientCallbackRequest::STATUS_COMPLETED]);
                    $req->client?->refreshCallbackStatus();
                }
            }

            Log::info('[callback-requests:process] Cola creada (se ejecutará a la hora programada)', [
                'queue_id' => $queue->id,
                'agent_id' => $agent->id,
                'channel' => $channel,
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'callbacks_count' => $group->count(),
            ]);
            $this->info("Cola {$queue->id} creada para {$group->count()} callback(s) - agente {$agent->id}, {$channel} - ejecución: {$scheduledAt->format('Y-m-d H:i')} ({$tzName})");
        }

        Log::info('[callback-requests:process] Tarea completada', ['total_requests' => $requests->count()]);
        return self::SUCCESS;
    }
}
