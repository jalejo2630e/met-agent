<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\Client;
use App\Models\ClientCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackRequestApiController extends Controller
{
    /**
     * POST /api/agents/{agent}/callback-requests
     * Programa una llamada o mensaje para una fecha específica.
     * Auth: Bearer token o X-Api-Key con API key del agente.
     */
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        $apiKey = $request->bearerToken() ?? $request->header('X-Api-Key');

        if (! $apiKey) {
            return response()->json(['error' => 'API key requerida (Bearer token o header X-Api-Key)'], 401);
        }

        $keyModel = AgentApiKey::where('agent_id', $agent->id)
            ->where('key_prefix', substr($apiKey, 0, 8))
            ->first();

        if (! $keyModel || ! $keyModel->matches($apiKey)) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $keyModel->update(['last_used_at' => now()]);

        $todayColombia = now('America/Bogota')->toDateString();

        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'phone' => 'nullable|string|max:50',
            'document' => 'nullable|string|max:100',
            'scheduled_date' => 'required|date|after_or_equal:'.$todayColombia,
            'scheduled_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'channel' => 'required|in:call,whatsapp',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['client_id']) && empty($validated['phone']) && empty($validated['document'])) {
            return response()->json([
                'success' => false,
                'error' => 'Proporciona client_id, phone o document para identificar al cliente.',
            ], 422);
        }

        $client = null;
        if (! empty($validated['client_id'])) {
            $client = Client::where('id', $validated['client_id'])->where('agent_id', $agent->id)->first();
        }
        if (! $client && ! empty($validated['phone'])) {
            $phone = preg_replace('/\D/', '', $validated['phone']);
            if ($phone !== '') {
                $client = Client::where('agent_id', $agent->id)->where('phone', $phone)->first();
            }
        }
        if (! $client && ! empty($validated['document'])) {
            $client = Client::where('agent_id', $agent->id)->where('document', $validated['document'])->first();
        }

        if (! $client) {
            return response()->json([
                'success' => false,
                'error' => 'Cliente no encontrado. Proporciona client_id, phone o document de un cliente existente.',
            ], 422);
        }

        if ($validated['channel'] === 'whatsapp' && ! $client->phone) {
            return response()->json([
                'success' => false,
                'error' => 'El cliente no tiene teléfono registrado para WhatsApp.',
            ], 422);
        }

        $config = $validated['channel'] === 'whatsapp' ? $agent->messageConfig : $agent->callConfig;
        if (! $config?->webhook_url) {
            return response()->json([
                'success' => false,
                'error' => 'No hay webhook configurado para '.($validated['channel'] === 'whatsapp' ? 'mensajes' : 'llamadas').'.',
            ], 422);
        }

        $dateStr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $validated['scheduled_date'])
            ? $validated['scheduled_date']
            : \Carbon\Carbon::parse($validated['scheduled_date'])->format('Y-m-d');

        $callback = ClientCallbackRequest::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'scheduled_date' => $dateStr,
            'scheduled_time' => $validated['scheduled_time'] ?? null,
            'channel' => $validated['channel'],
            'notes' => $validated['notes'] ?? null,
            'status' => ClientCallbackRequest::STATUS_PENDING,
            'created_by' => null,
        ]);

        $client->update(['status' => Client::STATUS_LLAMADA_PROGRAMADA]);

        $timeMsg = ! empty($validated['scheduled_time'])
            ? 'a las '.substr($validated['scheduled_time'], 0, 5)
            : 'a las 08:00 (por defecto)';

        return response()->json([
            'success' => true,
            'message' => "Callback programado correctamente. Se ejecutará el día indicado {$timeMsg} (hora Colombia).",
            'callback_request' => [
                'id' => $callback->id,
                'client_id' => $callback->client_id,
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'lastname' => $client->lastname,
                    'phone' => $client->phone,
                ],
                'scheduled_date' => $callback->scheduled_date->format('Y-m-d'),
                'scheduled_time' => $callback->scheduled_time,
                'channel' => $callback->channel,
                'notes' => $callback->notes,
                'status' => $callback->status,
            ],
        ], 201);
    }
}
