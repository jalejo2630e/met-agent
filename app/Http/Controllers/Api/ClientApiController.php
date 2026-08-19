<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\Client;
use App\Models\ClientCallbackRequest;
use App\Models\ClientContactLog;
use App\Models\ClientLoadDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientApiController extends Controller
{
    private function authenticateAgentApiKey(Request $request, Agent $agent): ?AgentApiKey
    {
        $apiKey = $request->bearerToken() ?? $request->header('X-Api-Key');
        if (! $apiKey) {
            return null;
        }

        $keyModel = AgentApiKey::where('agent_id', $agent->id)
            ->where('key_prefix', substr($apiKey, 0, 8))
            ->first();

        if (! $keyModel || ! $keyModel->matches($apiKey)) {
            return null;
        }

        $keyModel->update(['last_used_at' => now()]);

        return $keyModel;
    }

    /**
     * Busca un cliente del agente por teléfono (coincidencia exacta o por sufijos de dígitos, como en el panel).
     */
    private function findClientByPhoneForAgent(Agent $agent, string $phoneDigits): ?Client
    {
        if ($phoneDigits === '') {
            return null;
        }

        $base = Client::query()->where('agent_id', $agent->id);

        $client = (clone $base)->where('phone', $phoneDigits)->first();
        if ($client) {
            return $client;
        }

        return (clone $base)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(function (Client $c) use ($phoneDigits) {
                $stored = preg_replace('/\D/', '', (string) $c->phone);

                return $stored === $phoneDigits
                    || ($stored !== '' && $phoneDigits !== '' && (
                        str_ends_with($stored, $phoneDigits)
                        || str_ends_with($phoneDigits, $stored)
                    ));
            });
    }

    /**
     * GET|POST /api/agents/{agent}/clients/by-phone
     * Consulta un cliente completo por phone (query ?phone= o JSON/body phone).
     * Auth: Bearer token o X-Api-Key con API key del agente.
     */
    public function showByPhone(Request $request, Agent $agent): JsonResponse
    {
        $keyModel = $this->authenticateAgentApiKey($request, $agent);
        if (! $keyModel) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:50',
        ]);

        $phoneDigits = preg_replace('/\D/', '', $validated['phone']);
        if ($phoneDigits === '') {
            return response()->json(['error' => 'phone inválido'], 422);
        }

        $client = $this->findClientByPhoneForAgent($agent, $phoneDigits);
        if (! $client) {
            return response()->json(['success' => false, 'error' => 'Cliente no encontrado para ese teléfono'], 404);
        }

        $client->load([
            'loadDates' => fn ($q) => $q->limit(100)->latest('loaded_at'),
            'contactLogs' => fn ($q) => $q->limit(100)->latest('contacted_at'),
            'callbackRequests' => fn ($q) => $q->limit(50)->latest('scheduled_date'),
            'formResponse.answers',
        ]);

        // Preguntas del formulario del agente con la respuesta de este cliente (si la hay).
        $questions = $agent->questions()->get(['id', 'label', 'help_text', 'required', 'order']);
        $answersByQuestion = $client->formResponse
            ? $client->formResponse->answers->keyBy('agent_question_id')
            : collect();

        return response()->json([
            'success' => true,
            'client' => [
                'id' => $client->id,
                'agent_id' => $client->agent_id,
                'name' => $client->name,
                'lastname' => $client->lastname,
                'email' => $client->email,
                'phone' => $client->phone,
                'document_type' => $client->document_type,
                'document' => $client->document,
                'status' => $client->status,
                'custom_fields' => $client->custom_fields ?? [],
                'loaded_at' => $client->loaded_at?->toIso8601String(),
                'created_at' => $client->created_at?->toIso8601String(),
                'updated_at' => $client->updated_at?->toIso8601String(),
                'load_dates' => $client->loadDates->map(fn (ClientLoadDate $d) => [
                    'id' => $d->id,
                    'loaded_at' => $d->loaded_at?->toIso8601String(),
                    'source' => $d->source,
                ])->values()->all(),
                'contact_logs' => $client->contactLogs->map(fn (ClientContactLog $l) => [
                    'id' => $l->id,
                    'channel' => $l->channel,
                    'contacted_at' => $l->contacted_at?->toIso8601String(),
                ])->values()->all(),
                'callback_requests' => $client->callbackRequests->map(fn (ClientCallbackRequest $cb) => [
                    'id' => $cb->id,
                    'scheduled_date' => $cb->scheduled_date?->format('Y-m-d'),
                    'scheduled_time' => $cb->scheduled_time,
                    'channel' => $cb->channel,
                    'notes' => $cb->notes,
                    'status' => $cb->status,
                    'created_at' => $cb->created_at?->toIso8601String(),
                ])->values()->all(),
                'form' => [
                    'has_link' => (bool) $client->formResponse,
                    'url' => $client->formResponse ? route('public.form.show', $client->formResponse->token) : null,
                    'answered' => (bool) $client->formResponse?->submitted_at,
                    'submitted_at' => $client->formResponse?->submitted_at?->toIso8601String(),
                    'questions' => $questions->map(fn ($q) => [
                        'id' => $q->id,
                        'label' => $q->label,
                        'help_text' => $q->help_text,
                        'required' => $q->required,
                        'answer' => optional($answersByQuestion->get($q->id))->answer,
                    ])->values()->all(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/agents/{agent}/clients
     * Crea o actualiza un cliente desde N8N u otro sistema externo.
     * Auth: Bearer token o X-Api-Key con API key del agente.
     */
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        $keyModel = $this->authenticateAgentApiKey($request, $agent);
        if (! $keyModel) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'document_type' => 'nullable|string|max:50',
            'document' => 'nullable|string|max:100',
            'custom_fields' => 'nullable|array',
        ]);

        $customFields = $validated['custom_fields'] ?? [];
        unset($validated['custom_fields']);

        $client = null;
        if (! empty($validated['document'])) {
            $client = Client::where('agent_id', $agent->id)
                ->where('document', $validated['document'])
                ->first();
        }
        if (! $client && ! empty($validated['phone'])) {
            $phone = preg_replace('/\D/', '', $validated['phone']);
            if ($phone !== '') {
                $client = Client::where('agent_id', $agent->id)
                    ->where('phone', $phone)
                    ->first();
            }
        }

        if ($client) {
            $client->update(array_merge($validated, [
                'custom_fields' => array_merge($client->custom_fields ?? [], $customFields),
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado',
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'lastname' => $client->lastname,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'document_type' => $client->document_type,
                    'document' => $client->document,
                ],
            ]);
        }

        $now = now();
        $client = $agent->clients()->create(array_merge($validated, [
            'custom_fields' => $customFields ?: null,
            'loaded_at' => $now,
        ]));

        ClientLoadDate::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'loaded_at' => $now,
            'source' => ClientLoadDate::SOURCE_API,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado',
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'lastname' => $client->lastname,
                'email' => $client->email,
                'phone' => $client->phone,
                'document_type' => $client->document_type,
                'document' => $client->document,
            ],
        ], 201);
    }

    /**
     * PATCH /api/agents/{agent}/clients/custom-fields
     * Actualiza solamente custom_fields encontrando el cliente por phone.
     * Auth: Bearer token o X-Api-Key con API key del agente.
     */
    public function updateCustomFieldsByPhone(Request $request, Agent $agent): JsonResponse
    {
        $keyModel = $this->authenticateAgentApiKey($request, $agent);
        if (! $keyModel) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:50',
            'custom_fields' => 'required|array',
        ]);

        $phone = preg_replace('/\D/', '', $validated['phone']);
        if ($phone === '') {
            return response()->json(['error' => 'phone inválido'], 422);
        }

        $client = Client::where('agent_id', $agent->id)
            ->where('phone', $phone)
            ->first();

        if (! $client) {
            return response()->json(['error' => 'Cliente no encontrado para ese phone'], 404);
        }

        $client->update([
            'custom_fields' => array_merge($client->custom_fields ?? [], $validated['custom_fields']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom fields actualizados',
            'client' => [
                'id' => $client->id,
                'phone' => $client->phone,
                'custom_fields' => $client->custom_fields ?? [],
            ],
        ]);
    }

    /**
     * POST /api/clients/exists-by-phone
     * Valida si existe un cliente por phone filtrando por agent_id.
     * Auth: Bearer token o X-Api-Key con API key del agente.
     */
    public function existsByPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
            'phone' => 'required|string|max:50',
        ]);

        $agent = Agent::find((int) $validated['agent_id']);
        if (! $agent) {
            return response()->json(['error' => 'Agente no encontrado'], 404);
        }

        $keyModel = $this->authenticateAgentApiKey($request, $agent);
        if (! $keyModel) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $phone = preg_replace('/\D/', '', $validated['phone']);
        if ($phone === '') {
            return response()->json(['error' => 'phone inválido'], 422);
        }

        $client = Client::where('agent_id', $agent->id)
            ->where('phone', $phone)
            ->first(['id', 'name', 'lastname', 'custom_fields']);

        $fullName = null;
        if ($client) {
            $fullName = trim(implode(' ', array_filter([
                (string) ($client->name ?? ''),
                (string) ($client->lastname ?? ''),
            ])));
            if ($fullName === '') {
                $fullName = null;
            }
        }

        return response()->json([
            'success' => true,
            'exists' => (bool) $client,
            'client_id' => $client?->id,
            'name' => $fullName,
            'custom_fields' => $client ? ($client->custom_fields ?? []) : null,
        ]);
    }

    /**
     * POST /api/clients/duplicate
     * Duplica un cliente del agente origen en otro agente (mismo propietario).
     * Copia datos base (nombre, email, teléfono, documento); no copia custom_fields.
     * Auth: API key del agente origen (source_agent_id).
     */
    public function duplicateClient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'source_agent_id' => 'required|integer|exists:agents,id',
            'target_agent_id' => 'required|integer|exists:agents,id|different:source_agent_id',
        ]);

        $sourceAgent = Agent::find((int) $validated['source_agent_id']);
        $targetAgent = Agent::find((int) $validated['target_agent_id']);

        if (! $sourceAgent || ! $targetAgent) {
            return response()->json(['error' => 'Agente no encontrado'], 404);
        }

        if ($sourceAgent->user_id !== $targetAgent->user_id) {
            return response()->json(['error' => 'No autorizado a copiar clientes entre estos agentes'], 403);
        }

        $keyModel = $this->authenticateAgentApiKey($request, $sourceAgent);
        if (! $keyModel) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $client = Client::where('id', (int) $validated['client_id'])
            ->where('agent_id', $sourceAgent->id)
            ->first();

        if (! $client) {
            return response()->json(['error' => 'Cliente no encontrado en el agente origen'], 404);
        }

        $now = now();

        $newClient = Client::create([
            'agent_id' => $targetAgent->id,
            'name' => $client->name,
            'lastname' => $client->lastname,
            'email' => $client->email,
            'phone' => $client->phone,
            'document_type' => $client->document_type,
            'document' => $client->document,
            'custom_fields' => null,
            'loaded_at' => $now,
            'status' => Client::STATUS_NO_CONTACTADO,
        ]);

        ClientLoadDate::create([
            'client_id' => $newClient->id,
            'agent_id' => $targetAgent->id,
            'loaded_at' => $now,
            'source' => ClientLoadDate::SOURCE_API,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cliente duplicado',
            'client' => [
                'id' => $newClient->id,
                'agent_id' => $newClient->agent_id,
                'name' => $newClient->name,
                'lastname' => $newClient->lastname,
                'email' => $newClient->email,
                'phone' => $newClient->phone,
                'document_type' => $newClient->document_type,
                'document' => $newClient->document,
                'custom_fields' => $newClient->custom_fields ?? [],
            ],
        ], 201);
    }
}
