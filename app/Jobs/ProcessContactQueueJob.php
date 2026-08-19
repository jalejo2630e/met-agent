<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\ContactQueue;
use App\Services\ContactQueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContactQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Compatibilidad con dispatch()->onQueue()/onConnection() sin usar Queueable.
     * Evita conflictos de propiedad $queue en PHP 8.2+.
     */
    public ?string $queue = null;
    public ?string $connection = null;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public ContactQueue $contactQueue
    ) {}

    public function onQueue(?string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function onConnection(?string $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function handle(ContactQueueService $service): void
    {
        $queue = $this->contactQueue->fresh();
        if (! $queue || $queue->status !== ContactQueue::STATUS_PENDING) {
            Log::info('[ProcessContactQueueJob] Cola omitida (no existe o ya procesada)', [
                'queue_id' => $this->contactQueue->id,
                'status' => $queue?->status,
            ]);
            return;
        }

        Log::info('[ProcessContactQueueJob] Iniciando ejecución', [
            'queue_id' => $queue->id,
            'agent_id' => $queue->agent_id,
            'type' => $queue->type,
            'by_rules' => ! empty($queue->client_selection_rules),
        ]);
        $queue->update(['status' => ContactQueue::STATUS_PROCESSING]);

        $agent = $queue->agent;
        $agent->load(['callConfig', 'messageConfig', 'clientSourceEndpoints']);
        $type = $queue->type;

        $processed = 0;
        $failed = 0;
        $batchSize = 10;
        $delayMs = 500;

        $clientSelectionRules = $queue->client_selection_rules ?? [];
        $clientIds = $queue->client_ids ?? [];

        if (empty($clientSelectionRules) && empty($clientIds)) {
            Log::info('[ProcessContactQueueJob] Cola sin clientes ni reglas, marcada como completada', ['queue_id' => $queue->id]);
            $queue->update(['status' => ContactQueue::STATUS_COMPLETED]);
            return;
        }

        $query = Client::where('agent_id', $agent->id)
            ->withCount('contactLogs')
            ->withMax('contactLogs as last_contacted_at', 'contacted_at');

        if (! empty($clientSelectionRules)) {
            $query->limit(10000);
        } else {
            $query->whereIn('id', $clientIds);
        }

        $clients = $query->get();

        if ($type === ContactQueue::TYPE_WHATSAPP) {
            $clients = $clients->filter(fn (Client $c) => ! empty($c->phone));
        }

        $snapshot = $queue->schedule_snapshot ?? [];
        $runTime = $queue->next_run_at ? $queue->next_run_at->format('H:i') : null;

        if (! empty($clientSelectionRules)) {
            $rulesSnapshot = ['execution_rules' => $clientSelectionRules];
            $clients = $service->filterClientsByExecutionRules($clients, $rulesSnapshot, null);
        }

        $beforeCount = $clients->count();
        $clients = $service->filterClientsByExecutionRules($clients, $snapshot, $runTime);
        $afterCount = $clients->count();
        if ($afterCount < $beforeCount || $afterCount === 0) {
            Log::info('[ProcessContactQueueJob] Clientes filtrados por reglas', [
                'queue_id' => $queue->id,
                'before' => $beforeCount,
                'after' => $afterCount,
                'run_time' => $runTime,
            ]);
        }

        $idPlantilla = null;
        if ($type === ContactQueue::TYPE_WHATSAPP) {
            if (! empty($snapshot['from_callback'])) {
                $idPlantilla = $snapshot['callback_plantilla_id'] ?? $agent->messageConfig?->default_plantilla_id;
            } else {
                $idPlantilla = $snapshot['immediate_plantilla'] ?? null;
                if ($idPlantilla === null) {
                    $idPlantillaByTime = $snapshot['id_plantilla_by_time'] ?? [];
                    $idPlantilla = $runTime ? ($idPlantillaByTime[$runTime] ?? null) : null;
                }
                if ($idPlantilla === null) {
                    $idPlantilla = $agent->messageConfig?->default_plantilla_id;
                }
            }
        }

        foreach ($clients->chunk($batchSize) as $chunk) {
            $queue->refresh();
            if ($queue->status === ContactQueue::STATUS_CANCELLED) {
                Log::info('[ProcessContactQueueJob] Cola cancelada, deteniendo ejecución', [
                    'queue_id' => $queue->id,
                    'processed_so_far' => $processed,
                    'failed_so_far' => $failed,
                ]);
                $queue->processed_count = $processed;
                $queue->failed_count = $failed;
                $queue->last_run_at = now();
                $queue->status = ContactQueue::STATUS_CANCELLED;
                $queue->save();
                return;
            }

            foreach ($chunk as $client) {
                try {
                    if ($service->sendToWebhook($agent, $client, $type, $idPlantilla)) {
                        $processed++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable) {
                    $failed++;
                }
                usleep($delayMs * 1000);
            }
        }

        $queue->refresh();
        if ($queue->status === ContactQueue::STATUS_CANCELLED) {
            Log::info('[ProcessContactQueueJob] Cola cancelada durante ejecución, guardando progreso parcial', ['queue_id' => $queue->id]);
            $queue->processed_count = $processed;
            $queue->failed_count = $failed;
            $queue->last_run_at = now();
            $queue->status = ContactQueue::STATUS_CANCELLED;
            $queue->save();
            return;
        }

        $queue->processed_count = $processed;
        $queue->failed_count = $failed;
        $queue->last_run_at = now();

        Log::info('[ProcessContactQueueJob] Ejecución completada', [
            'queue_id' => $queue->id,
            'agent_id' => $queue->agent_id,
            'type' => $queue->type,
            'processed' => $processed,
            'failed' => $failed,
            'status' => $queue->isRecurring() ? 'recurring' : 'completed',
        ]);

        if ($queue->isRecurring()) {
            $nextRun = $service->computeNextRunForRecurring($queue);
            if ($nextRun) {
                $queue->next_run_at = $nextRun;
                $queue->status = ContactQueue::STATUS_PENDING;
            } else {
                $queue->status = ContactQueue::STATUS_COMPLETED;
                $queue->next_run_at = null;
            }
        } else {
            $queue->status = ContactQueue::STATUS_COMPLETED;
            $queue->next_run_at = null;
        }

        $queue->save();
    }
}
