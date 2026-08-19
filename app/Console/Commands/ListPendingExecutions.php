<?php

namespace App\Console\Commands;

use App\Models\ClientCallbackRequest;
use App\Models\ContactQueue;
use Illuminate\Console\Command;

class ListPendingExecutions extends Command
{
    protected $signature = 'executions:list
                            {--callbacks : Solo callbacks programados}
                            {--queues : Solo colas de contacto}';

    protected $description = 'Lista ejecuciones pendientes (callbacks y colas)';

    public function handle(): int
    {
        $showCallbacks = $this->option('callbacks') || (! $this->option('callbacks') && ! $this->option('queues'));
        $showQueues = $this->option('queues') || (! $this->option('callbacks') && ! $this->option('queues'));

        if ($showCallbacks) {
            $this->listCallbacks();
        }

        if ($showQueues) {
            $this->listQueues();
        }

        return self::SUCCESS;
    }

    private function listCallbacks(): void
    {
        $today = now()->toDateString();

        $pending = ClientCallbackRequest::where('status', ClientCallbackRequest::STATUS_PENDING)
            ->whereDate('scheduled_date', '>=', $today)
            ->with(['client:id,name,phone', 'agent:id,name'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        $this->newLine();
        $this->info('Callbacks programados pendientes:');
        $this->line(str_repeat('-', 60));

        if ($pending->isEmpty()) {
            $this->warn('  No hay callbacks pendientes.');
            return;
        }

        foreach ($pending as $r) {
            $clientName = $r->client?->name ?? "cliente #{$r->client_id}";
            $agentName = $r->agent?->name ?? "agente #{$r->agent_id}";
            $time = $r->scheduled_time ?? '—';
            $channel = $r->channel === 'call' ? 'Llamada' : 'WhatsApp';
            $this->line("  ID {$r->id} | {$r->scheduled_date->format('Y-m-d')} {$time} | {$clientName} | {$agentName} | {$channel}");
        }

        $this->line(str_repeat('-', 60));
        $this->info("Total: {$pending->count()} callback(s)");
    }

    private function listQueues(): void
    {
        $queues = ContactQueue::where('status', ContactQueue::STATUS_PENDING)
            ->with('agent:id,name')
            ->orderBy('next_run_at')
            ->get();

        $this->newLine();
        $this->info('Colas de contacto pendientes:');
        $this->line(str_repeat('-', 60));

        if ($queues->isEmpty()) {
            $this->warn('  No hay colas pendientes.');
            return;
        }

        $now = now();
        foreach ($queues as $q) {
            $agentName = $q->agent?->name ?? "agente #{$q->agent_id}";
            $nextRun = $q->next_run_at?->format('Y-m-d H:i') ?? '—';
            $ready = $q->next_run_at && $q->next_run_at <= $now ? ' [LISTA]' : '';
            $count = count($q->client_ids ?? []);
            $this->line("  Cola {$q->id} | {$q->type} | {$agentName} | {$count} cliente(s) | next: {$nextRun}{$ready}");
        }

        $this->line(str_repeat('-', 60));
        $this->info("Total: {$queues->count()} cola(s)");
    }
}
