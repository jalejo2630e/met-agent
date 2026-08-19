<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\Client;
use App\Models\ClientLoadDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectDataController extends Controller
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        $apiKey = $request->bearerToken() ?? $request->header('X-Api-Key');

        if (! $apiKey) {
            return response()->json(['error' => 'API key requerida'], 401);
        }

        $keyModel = AgentApiKey::where('agent_id', $agent->id)
            ->where('key_prefix', substr($apiKey, 0, 8))
            ->first();

        if (! $keyModel || ! $keyModel->matches($apiKey)) {
            return response()->json(['error' => 'API key inválida'], 401);
        }

        $keyModel->update(['last_used_at' => now()]);

        $variables = $agent->dataVariables;
        $rules = [
            'phone' => ['required', 'string', 'max:50'],
        ];
        foreach ($variables as $v) {
            $rules[$v->name] = $v->required ? 'required' : 'nullable';
        }

        $validated = $request->validate($rules);

        $phoneRaw = $validated['phone'];
        $phone = preg_replace('/\D/', '', $phoneRaw);
        unset($validated['phone']);

        if ($phone === '') {
            return response()->json(['error' => 'El phone no puede quedar vacío después de normalizar'], 422);
        }

        $client = Client::where('agent_id', $agent->id)->where('phone', $phone)->first();

        if ($client) {
            $client->update([
                'custom_fields' => array_merge($client->custom_fields ?? [], $validated),
            ]);
        } else {
            $client = Client::create([
                'agent_id' => $agent->id,
                'phone' => $phone,
                'name' => 'Recolectado',
                'lastname' => '',
                'email' => 'recolectado-'.uniqid().'@temp.local',
                'custom_fields' => $validated,
                'loaded_at' => now(),
            ]);
            ClientLoadDate::create([
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'loaded_at' => now(),
                'source' => ClientLoadDate::SOURCE_API,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Datos recolectados correctamente',
        ]);
    }
}
