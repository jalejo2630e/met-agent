<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\ConversationExtraction;
use App\Models\TwilioMessage;
use App\Services\ConversationExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de mensajes recibidos por el webhook de Twilio (WhatsApp/SMS).
 * Agrupa por número y marca si ya existe un cliente, para poder crearlo desde ahí.
 */
class TwilioInboxController extends Controller
{
    public function index(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $messages = TwilioMessage::where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $conversations = $messages->groupBy('from_number')->map(function ($msgs, $from) use ($agent) {
            $last = $msgs->first();
            $profile = optional($msgs->firstWhere(fn ($m) => filled($m->profile_name)))->profile_name;
            $client = $this->findClient($agent, (string) $from);

            return [
                'from_number' => $from,
                'profile_name' => $profile,
                'last_message' => [
                    'body' => $last?->body,
                    'direction' => $last?->direction,
                    'created_at' => optional($last?->created_at)->toIso8601String(),
                ],
                'messages_count' => $msgs->count(),
                'inbound_count' => $msgs->where('direction', 'inbound')->count(),
                'client' => $client
                    ? ['id' => $client->id, 'name' => trim($client->name.' '.$client->lastname)]
                    : null,
            ];
        })->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function thread(Agent $agent, Request $request): JsonResponse
    {
        $this->authorize('view', $agent);

        $from = (string) $request->query('from', '');

        $messages = TwilioMessage::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'direction', 'body', 'created_at']);

        return response()->json([
            'messages' => $messages,
            'extraction' => $this->extractionPayload($agent, $from),
        ]);
    }

    /**
     * Fuerza una nueva extracción de variables de recolección para la conversación.
     */
    public function extract(Agent $agent, Request $request, ConversationExtractionService $service): JsonResponse
    {
        $this->authorize('view', $agent);

        $from = (string) $request->input('from', '');
        if ($from !== '') {
            $service->extractForConversation($agent, $from);
        }

        return response()->json(['extraction' => $this->extractionPayload($agent, $from)]);
    }

    /**
     * Variables de recolección definidas + valores extraídos de la conversación.
     *
     * @return array{variables: array<int, array<string, mixed>>, values: array<string, mixed>, updated_at: ?string}
     */
    private function extractionPayload(Agent $agent, string $from): array
    {
        $variables = $agent->extractionVariables()->orderBy('order')->orderBy('id')->get()
            ->map(fn ($v) => [
                'name' => $v->name,
                'label' => $v->label,
                'description' => $v->description,
                'type' => $v->type,
            ])->values();

        $extraction = ConversationExtraction::where('agent_id', $agent->id)
            ->where('from_number', $from)
            ->first();

        return [
            'variables' => $variables->all(),
            'values' => (array) ($extraction?->values ?? []),
            'updated_at' => $extraction?->updated_at?->toIso8601String(),
        ];
    }

    private function findClient(Agent $agent, string $from): ?Client
    {
        $digits = preg_replace('/\D/', '', $from);
        if ($digits === '') {
            return null;
        }
        $last10 = substr($digits, -10);

        return Client::where('agent_id', $agent->id)
            ->where(function ($q) use ($digits, $last10) {
                $q->where('phone', $digits)->orWhere('phone', 'like', '%'.$last10);
            })
            ->first();
    }
}
