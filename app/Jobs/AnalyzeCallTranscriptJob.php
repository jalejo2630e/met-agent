<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Services\CallAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Analiza una transcripción de llamada en segundo plano (usa el queue worker
 * que ya corre en supervisord). Se despacha desde AnalyzeCallsCommand o donde
 * se necesite procesar en lote sin bloquear la petición.
 */
class AnalyzeCallTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public int $agentId,
        public string $conversationId,
        public string $transcript,
        public ?string $phone = null,
    ) {
    }

    public function handle(CallAnalysisService $service): void
    {
        if (! $service->isConfigured()) {
            return;
        }

        $agent = Agent::find($this->agentId);

        if (! $agent) {
            return;
        }

        $service->analyze($agent, $this->conversationId, $this->transcript, $this->phone);
    }
}
