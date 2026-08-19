<?php

namespace App\Jobs;

use App\Models\ContactQueue;
use App\Services\CallBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Despacha las siguientes llamadas de una cola (ventana deslizante) desde el
 * queue worker, para no bloquear la petición del webhook post-call.
 */
class DispatchQueuedCallsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $queueId) {}

    public function handle(CallBatchService $service): void
    {
        $queue = ContactQueue::find($this->queueId);
        if ($queue) {
            $service->dispatchNext($queue);
        }
    }
}
