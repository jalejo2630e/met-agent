<?php

namespace App\Services;

use App\Jobs\DispatchQueuedCallsJob;
use App\Models\Client;
use App\Models\ClientContactLog;
use App\Models\ContactQueue;
use App\Models\ContactQueueCall;

/**
 * Ejecuta una ContactQueue de tipo "call" como una VENTANA DESLIZANTE de
 * concurrencia: mantiene hasta N llamadas en curso (por defecto 10). Cada llamada
 * queda "in_flight" hasta que el webhook post-call de ElevenLabs confirma su
 * transcripción; entonces se libera el cupo y se despacha la siguiente.
 */
class CallBatchService
{
    public const DEFAULT_CONCURRENCY = 10;

    public function __construct(private ContactQueueService $queues) {}

    /**
     * Prepara la cola (crea un ContactQueueCall por cliente) y arranca el despacho.
     */
    public function start(ContactQueue $queue): void
    {
        $queue->loadMissing('agent');
        $agent = $queue->agent;
        if (! $agent) {
            return;
        }

        if ($queue->calls()->exists()) {
            $this->dispatchNext($queue);

            return;
        }

        $agent->loadMissing(['callConfig', 'clientSourceEndpoints']);

        $query = Client::where('agent_id', $agent->id)
            ->withCount('contactLogs')
            ->withMax('contactLogs as last_contacted_at', 'contacted_at');

        $rules = $queue->client_selection_rules ?? [];
        if (! empty($rules)) {
            $clients = $this->queues->filterClientsByExecutionRules(
                $query->limit(10000)->get(),
                ['execution_rules' => $rules],
                null,
            );
        } else {
            $clients = $query->whereIn('id', $queue->client_ids ?? [])->get();
        }

        $clients = $clients->filter(fn (Client $c) => ! empty($c->phone));

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = [
                'contact_queue_id' => $queue->id,
                'agent_id' => $agent->id,
                'client_id' => $client->id,
                'phone' => $client->phone,
                'status' => ContactQueueCall::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            ContactQueueCall::insert($rows);
        }

        $queue->update([
            'status' => ContactQueue::STATUS_PROCESSING,
            'next_run_at' => null,
        ]);

        $this->dispatchNext($queue);
    }

    /**
     * Despacha las llamadas pendientes necesarias para llenar la ventana de concurrencia.
     */
    public function dispatchNext(ContactQueue $queue): void
    {
        $queue = $queue->fresh();
        if (! $queue || $queue->status === ContactQueue::STATUS_CANCELLED) {
            return;
        }

        $queue->loadMissing('agent');
        $agent = $queue->agent;
        if (! $agent) {
            return;
        }

        $concurrency = max(1, (int) ($queue->concurrency ?: self::DEFAULT_CONCURRENCY));
        $inFlight = $queue->calls()->where('status', ContactQueueCall::STATUS_IN_FLIGHT)->count();
        $available = $concurrency - $inFlight;

        $failed = 0;
        if ($available > 0) {
            $pending = $queue->calls()
                ->where('status', ContactQueueCall::STATUS_PENDING)
                ->orderBy('id')
                ->limit($available)
                ->get();

            foreach ($pending as $call) {
                $client = $call->client ?: ($call->client_id ? Client::find($call->client_id) : null);
                if (! $client) {
                    $call->update(['status' => ContactQueueCall::STATUS_FAILED, 'completed_at' => now()]);
                    $failed++;

                    continue;
                }

                $call->update([
                    'status' => ContactQueueCall::STATUS_IN_FLIGHT,
                    'dispatched_at' => now(),
                    'phone' => $client->phone ?: $call->phone,
                ]);

                try {
                    $ok = $this->queues->sendCall($agent, $client, ['queue_call_id' => $call->id]);
                } catch (\Throwable $e) {
                    report($e);
                    $ok = false;
                }

                if ($ok) {
                    ClientContactLog::create([
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'channel' => ClientContactLog::CHANNEL_CALL,
                        'contacted_at' => now(),
                    ]);
                    $queue->increment('processed_count');
                } else {
                    $call->update(['status' => ContactQueueCall::STATUS_FAILED, 'completed_at' => now()]);
                    $queue->increment('failed_count');
                    $failed++;
                }
            }
        }

        // Si hubo fallos al enviar, esos cupos quedaron libres: rellenar.
        if ($failed > 0) {
            $this->dispatchNext($queue->fresh());

            return;
        }

        $remaining = $queue->calls()
            ->whereIn('status', [ContactQueueCall::STATUS_PENDING, ContactQueueCall::STATUS_IN_FLIGHT])
            ->count();

        if ($remaining === 0) {
            $this->complete($queue);
        }
    }

    /**
     * Llega el post-call de ElevenLabs: marca la llamada como completada y libera el cupo.
     */
    public function handlePostCall(int $agentId, ?string $conversationId, ?string $phone, ?int $queueCallId): void
    {
        $call = null;

        if ($queueCallId) {
            $call = ContactQueueCall::where('id', $queueCallId)
                ->where('agent_id', $agentId)
                ->where('status', ContactQueueCall::STATUS_IN_FLIGHT)
                ->first();
        }

        if (! $call && $phone) {
            $digits = preg_replace('/\D/', '', $phone);
            $call = ContactQueueCall::where('agent_id', $agentId)
                ->where('status', ContactQueueCall::STATUS_IN_FLIGHT)
                ->when($digits !== '', fn ($q) => $q->where(function ($qq) use ($phone, $digits) {
                    $qq->where('phone', $phone)->orWhere('phone', 'like', '%'.$digits.'%');
                }))
                ->orderBy('dispatched_at')
                ->first();
        }

        if (! $call) {
            return;
        }

        $call->update([
            'status' => ContactQueueCall::STATUS_DONE,
            'conversation_id' => $conversationId,
            'completed_at' => now(),
        ]);

        DispatchQueuedCallsJob::dispatch($call->contact_queue_id);
    }

    /**
     * Libera cupos de llamadas que quedaron "in_flight" sin post-call (timeout).
     * Se invoca desde el scheduler (contact-queues:process).
     */
    public function releaseTimeouts(int $minutes = 15): void
    {
        $cutoff = now()->subMinutes($minutes);

        $stale = ContactQueueCall::where('status', ContactQueueCall::STATUS_IN_FLIGHT)
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', $cutoff)
            ->get();

        $queueIds = [];
        foreach ($stale as $call) {
            $call->update(['status' => ContactQueueCall::STATUS_TIMED_OUT, 'completed_at' => now()]);
            $queueIds[$call->contact_queue_id] = true;
        }

        foreach (array_keys($queueIds) as $queueId) {
            DispatchQueuedCallsJob::dispatch($queueId);
        }
    }

    protected function complete(ContactQueue $queue): void
    {
        if ($queue->isRecurring()) {
            $next = $this->queues->computeNextRunForRecurring($queue);
            if ($next) {
                $queue->calls()->delete();
                $queue->update([
                    'status' => ContactQueue::STATUS_PENDING,
                    'next_run_at' => $next,
                    'last_run_at' => now(),
                ]);

                return;
            }
        }

        $queue->update([
            'status' => ContactQueue::STATUS_COMPLETED,
            'next_run_at' => null,
            'last_run_at' => now(),
        ]);
    }
}
