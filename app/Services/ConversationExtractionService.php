<?php

namespace App\Services;

use App\Ai\Agents\ConversationExtractor;
use App\Models\Agent;
use App\Models\AiUsageLog;
use App\Models\ConversationExtraction;
use App\Models\TwilioMessage;
use Illuminate\Support\Facades\Log;

/**
 * Ejecuta el agente ConversationExtractor sobre el historial de una conversación
 * (por número) y guarda/actualiza los valores extraídos en conversation_extractions.
 */
class ConversationExtractionService
{
    public function isConfigured(): bool
    {
        return (string) config('ai.providers.'.config('ai.default').'.key', '') !== '';
    }

    /**
     * Extrae las variables definidas para el agente a partir de la conversación
     * con $from. Best-effort: si no hay variables, no está configurada la IA, o
     * falla, no lanza excepción.
     */
    public function extractForConversation(Agent $agent, string $from): ?ConversationExtraction
    {
        $variables = $agent->extractionVariables()->orderBy('order')->orderBy('id')->get();
        if ($variables->isEmpty() || ! $this->isConfigured()) {
            return null;
        }

        $messages = TwilioMessage::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->orderBy('id')
            ->limit(60)
            ->get(['direction', 'body']);

        if ($messages->isEmpty()) {
            return null;
        }

        $transcript = $messages
            ->map(fn (TwilioMessage $m) => ($m->direction === 'outbound' ? 'Asistente' : 'Cliente').': '.trim((string) $m->body))
            ->implode("\n");

        try {
            $response = (new ConversationExtractor($variables, (string) $agent->name))
                ->prompt("Extrae los datos de la siguiente conversación:\n\n".$transcript);

            AiUsageLog::record($agent->id, $from, 'extraction', $response->meta->model ?? null, $response->usage->promptTokens, $response->usage->completionTokens);

            /** @var array<string, mixed> $data */
            $data = json_decode((string) $response, true) ?: [];
        } catch (\Throwable $e) {
            Log::warning('Extracción de conversación falló', [
                'agent_id' => $agent->id,
                'from' => $from,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        // Solo conserva las claves definidas y normaliza vacíos.
        $allowed = $variables->pluck('name')->all();
        $clean = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            $clean[$key] = $value;
        }

        return ConversationExtraction::updateOrCreate(
            ['agent_id' => $agent->id, 'from_number' => $from],
            ['values' => $clean],
        );
    }

    /**
     * Adjunta a cada cliente las variables extraídas de su conversación de texto
     * (tabla conversation_extractions), cruzando por teléfono. El cruce usa los
     * últimos 10 dígitos, igual que el resto de la app (el from_number de WhatsApp
     * trae código de país `+57...` y el phone del cliente puede no traerlo).
     *
     * Deja en cada cliente el atributo `extracted_variables` (objeto clave=>valor).
     *
     * @param  iterable<\App\Models\Client>  $clients
     */
    public function attachToClients(Agent $agent, iterable $clients): void
    {
        $map = [];
        foreach (ConversationExtraction::where('agent_id', $agent->id)->get(['from_number', 'values']) as $extraction) {
            $digits = preg_replace('/\D/', '', (string) $extraction->from_number);
            if ($digits === '') {
                continue;
            }
            $map[substr($digits, -10)] = $extraction->values ?? [];
        }

        foreach ($clients as $client) {
            $digits = preg_replace('/\D/', '', (string) $client->phone);
            $key = $digits !== '' ? substr($digits, -10) : '';
            $client->extracted_variables = ($key !== '' && isset($map[$key])) ? $map[$key] : (object) [];
        }
    }
}
