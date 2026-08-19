<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Client;
use App\Models\ClientCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientCallbackRequestController extends Controller
{
    public function index(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $status = $request->get('status', 'pending');
        $clientId = $request->get('client_id');
        $query = ClientCallbackRequest::where('agent_id', $agent->id)
            ->with(['client:id,name,lastname,phone,email', 'createdBy:id,name'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if (in_array($status, ['pending', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $requests = $query->limit(100)->get();

        return response()->json([
            'server_time' => now()->format('Y-m-d H:i:s'),
            'server_timezone' => config('app.timezone'),
            'bogota_time' => now('America/Bogota')->format('Y-m-d H:i:s'),
            'callback_requests' => $requests->map(fn ($r) => [
                'id' => $r->id,
                'client_id' => $r->client_id,
                'client' => $r->client ? [
                    'id' => $r->client->id,
                    'name' => $r->client->name,
                    'lastname' => $r->client->lastname,
                    'phone' => $r->client->phone,
                    'email' => $r->client->email,
                ] : null,
                'scheduled_date' => $r->scheduled_date?->format('Y-m-d'),
                'scheduled_time' => $r->scheduled_time,
                'channel' => $r->channel,
                'notes' => $r->notes,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
                'created_by' => $r->createdBy?->name,
            ]),
        ]);
    }

    public function store(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $todayColombia = now('America/Bogota')->toDateString();

        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'scheduled_date' => 'required|date|after_or_equal:'.$todayColombia,
            'scheduled_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'channel' => 'required|in:call,whatsapp',
            'notes' => 'nullable|string|max:1000',
        ]);

        $client = Client::where('id', $validated['client_id'])->where('agent_id', $agent->id)->firstOrFail();

        if ($validated['channel'] === 'whatsapp' && ! $client->phone) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene teléfono registrado para WhatsApp.',
            ], 422);
        }

        $config = $validated['channel'] === 'whatsapp' ? $agent->messageConfig : $agent->callConfig;
        if (! $config?->webhook_url) {
            return response()->json([
                'success' => false,
                'message' => 'No hay webhook configurado para '.($validated['channel'] === 'whatsapp' ? 'mensajes' : 'llamadas').'.',
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
            'created_by' => $request->user()?->id,
        ]);

        $client->update(['status' => Client::STATUS_LLAMADA_PROGRAMADA]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de callback programada correctamente.',
            'callback_request' => [
                'id' => $callback->id,
                'client_id' => $callback->client_id,
                'scheduled_date' => $callback->scheduled_date->format('Y-m-d'),
                'scheduled_time' => $callback->scheduled_time,
                'channel' => $callback->channel,
                'notes' => $callback->notes,
                'status' => $callback->status,
            ],
        ]);
    }

    public function cancel(Agent $agent, ClientCallbackRequest $callbackRequest): JsonResponse
    {
        $this->authorize('view', $agent);

        if ($callbackRequest->agent_id !== $agent->id) {
            abort(404);
        }

        if ($callbackRequest->status !== ClientCallbackRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden cancelar solicitudes pendientes.',
            ], 422);
        }

        $callbackRequest->update(['status' => ClientCallbackRequest::STATUS_CANCELLED]);

        $callbackRequest->client?->refreshCallbackStatus();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud cancelada.',
        ]);
    }
}
