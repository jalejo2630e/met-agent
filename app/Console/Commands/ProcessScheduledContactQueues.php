<?php

namespace App\Console\Commands;

use App\Jobs\ProcessContactQueueJob;
use App\Models\ContactQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledContactQueues extends Command
{
    protected $signature = 'contact-queues:process';

    protected $description = 'Dispara los jobs de colas programadas que deben ejecutarse ahora';

    public function handle(): int
    {
        $now = now();
        Log::info('[contact-queues:process] Iniciando búsqueda de colas pendientes', [
            'now_utc' => $now->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone'),
        ]);

        $queues = ContactQueue::where('status', ContactQueue::STATUS_PENDING)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->get();

        if ($queues->isEmpty()) {
            Log::info('[contact-queues:process] No hay colas listas para ejecutar');
            return self::SUCCESS;
        }

        foreach ($queues as $queue) {
            ProcessContactQueueJob::dispatch($queue);
            Log::info('[contact-queues:process] Cola despachada', [
                'queue_id' => $queue->id,
                'agent_id' => $queue->agent_id,
                'type' => $queue->type,
                'next_run_at' => $queue->next_run_at?->toIso8601String(),
                'now' => $now->toIso8601String(),
            ]);
            $this->info("Cola {$queue->id} (agente {$queue->agent_id}, {$queue->type}) despachada.");
        }

        Log::info('[contact-queues:process] Tarea completada', ['queues_dispatched' => $queues->count()]);
        return self::SUCCESS;
    }
}
