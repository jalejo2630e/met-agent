<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeCallTranscriptJob;
use App\Models\CallAnalysis;
use App\Models\RegistroEscritoLlamada;
use App\Services\CallAnalysisService;
use App\Support\CallTranscriptsConnection;
use App\Support\TranscriptNormalizer;
use Illuminate\Console\Command;

/**
 * Backfill: analiza con IA las transcripciones de llamadas que aún no tienen
 * un CallAnalysis. Solo cubre el origen interno (Eloquent); si las
 * transcripciones están en Supabase (solo lectura) se indica y se omite.
 */
class AnalyzeCallsCommand extends Command
{
    protected $signature = 'ai:analyze-calls
        {agent? : ID del agente a procesar (opcional)}
        {--limit=50 : Máximo de transcripciones a procesar}
        {--sync : Ejecutar de forma síncrona en vez de encolar}';

    protected $description = 'Analiza transcripciones de llamadas con el AI SDK (resumen + alertas)';

    public function handle(CallAnalysisService $service): int
    {
        if (! $service->isConfigured()) {
            $this->warn('El proveedor de IA no está configurado (ai.default / OPENAI_API_KEY). Nada que hacer.');

            return self::SUCCESS;
        }

        if (CallTranscriptsConnection::usesRest()) {
            $this->warn('Las transcripciones están en Supabase (solo lectura). El backfill por comando solo soporta el origen interno.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $agentId = $this->argument('agent');

        $query = RegistroEscritoLlamada::query()
            ->whereNotNull('transcript')
            ->orderByDesc('created_at');

        if ($agentId) {
            $query->where('agent_id', (string) $agentId);
        }

        $dispatched = 0;

        foreach ($query->limit($limit * 3)->get() as $row) {
            if ($dispatched >= $limit) {
                break;
            }

            $rowAgentId = (int) $row->agent_id;
            if ($rowAgentId <= 0) {
                continue;
            }

            $conversationId = (string) ($row->conversation_id ?: $row->id);

            $exists = CallAnalysis::where('agent_id', $rowAgentId)
                ->where('conversation_id', $conversationId)
                ->exists();

            if ($exists) {
                continue;
            }

            $transcript = TranscriptNormalizer::toText($row->transcript);
            if (trim($transcript) === '') {
                continue;
            }

            if ($this->option('sync')) {
                $service->analyze(
                    \App\Models\Agent::findOrFail($rowAgentId),
                    $conversationId,
                    $transcript,
                    $row->phone,
                );
            } else {
                AnalyzeCallTranscriptJob::dispatch($rowAgentId, $conversationId, $transcript, $row->phone);
            }

            $dispatched++;
        }

        $this->info(($this->option('sync') ? 'Analizadas' : 'Encoladas').": {$dispatched} llamada(s).");

        return self::SUCCESS;
    }
}
