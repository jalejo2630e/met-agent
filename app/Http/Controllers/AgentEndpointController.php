<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentEndpoint;
use App\Models\AgentEndpointLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgentEndpointController extends Controller
{
    public function test(Request $request, Agent $agent, AgentEndpoint $endpoint): JsonResponse
    {
        $this->authorize('update', $agent);

        if ($endpoint->agent_id !== $agent->id) {
            abort(404);
        }

        $body = $request->input('body');
        $parsedBody = null;

        if ($body) {
            $parsedBody = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'error' => 'JSON inválido en el body',
                ], 400);
            }
        }

        try {
            $headers = $endpoint->headers ?? [];
            $http = Http::withHeaders($headers)->timeout(30);

            if ($endpoint->method === 'GET') {
                $response = $http->get($endpoint->url);
            } else {
                $payload = $parsedBody ?? ($body ? json_decode($body, true) : []);
                $response = $http->post($endpoint->url, $payload ?: []);
            }

            $success = $response->successful();
            AgentEndpointLog::create([
                'agent_endpoint_id' => $endpoint->id,
                'source' => 'test',
                'request_method' => $endpoint->method,
                'request_url' => $endpoint->url,
                'request_headers' => $headers,
                'request_body' => $body,
                'response_status' => $response->status(),
                'response_body' => is_string($response->body()) ? mb_substr($response->body(), 0, 50000) : json_encode($response->json()),
                'success' => $success,
            ]);

            return response()->json([
                'success' => $success,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'headers' => $response->headers(),
            ]);
        } catch (\Throwable $e) {
            AgentEndpointLog::create([
                'agent_endpoint_id' => $endpoint->id,
                'source' => 'test',
                'request_method' => $endpoint->method,
                'request_url' => $endpoint->url,
                'request_headers' => $endpoint->headers ?? [],
                'request_body' => $body,
                'error_message' => $e->getMessage(),
                'success' => false,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function update(Agent $agent, AgentEndpoint $endpoint, Request $request)
    {
        $this->authorize('update', $agent);

        if ($endpoint->agent_id !== $agent->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'method' => 'required|in:GET,POST',
            'headers' => 'nullable|array',
            'client_parameter' => 'required|string|max:255',
            'response_mapping' => 'nullable|array',
        ]);

        $endpoint->update($validated);

        return back()->with('success', 'Endpoint actualizado.');
    }
}
