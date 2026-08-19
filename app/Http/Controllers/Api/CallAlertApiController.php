<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SystemMarkdownMail;
use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\AlertaCategoria;
use App\Models\AlertaLlamada;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CallAlertApiController extends Controller
{
    /**
     * POST /api/agents/{agent}/call-alerts
     * Registra una alerta de llamada para un cliente (identificado por teléfono).
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

        $validated = $request->validate([
            'phone' => 'required|string|max:50',
            'numero_llamada' => 'nullable|integer|min:0',
            'paso_llamada' => 'nullable|integer|min:0',
            'descripcion' => 'nullable|string|max:5000',
            'categoria' => 'nullable|string|max:100',
        ]);

        // Resolver categoría (opcional) por slug o nombre; si no existe, queda sin categoría.
        $categoria = null;
        $categoriaRaw = trim((string) ($validated['categoria'] ?? ''));
        if ($categoriaRaw !== '') {
            $categoria = AlertaCategoria::where('slug', Str::slug($categoriaRaw))
                ->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($categoriaRaw)])
                ->first();
        }

        // Vincular al cliente por teléfono (dígitos) dentro de la empresa; si no hay, se guarda igual.
        $phoneDigits = preg_replace('/\D/', '', $validated['phone']);
        $client = $phoneDigits !== ''
            ? Client::where('agent_id', $agent->id)->where('phone', $phoneDigits)->first()
            : null;

        $alerta = AlertaLlamada::create([
            'client_id' => $client?->id,
            'agent_id' => $agent->id,
            'alerta_categoria_id' => $categoria?->id,
            'phone' => $validated['phone'],
            'numero_llamada' => $validated['numero_llamada'] ?? null,
            'paso_llamada' => $validated['paso_llamada'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        $this->notifyByEmail($agent, $alerta, $categoria, $client);

        return response()->json([
            'success' => true,
            'message' => 'Alerta de llamada registrada correctamente.',
            'call_alert' => [
                'id' => $alerta->id,
                'client_id' => $alerta->client_id,
                'phone' => $alerta->phone,
                'numero_llamada' => $alerta->numero_llamada,
                'paso_llamada' => $alerta->paso_llamada,
                'descripcion' => $alerta->descripcion,
                'categoria' => $categoria?->nombre,
                'created_at' => $alerta->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Notifica a los correos configurados en "Correos de alerta de llamada"
     * usando la plantilla de correo del sistema. No interrumpe la respuesta si falla.
     */
    private function notifyByEmail(Agent $agent, AlertaLlamada $alerta, ?AlertaCategoria $categoria, ?Client $client): void
    {
        $emails = json_decode((string) (Setting::get('call_alert_emails') ?? '[]'), true);
        $emails = is_array($emails)
            ? array_values(array_filter(array_map('trim', $emails)))
            : [];

        if ($emails === []) {
            return;
        }

        $clientName = $client ? trim($client->name.' '.($client->lastname ?? '')) : '';

        $bodyLines = [
            'Se registró una nueva alerta de llamada.',
            'Empresa: '.$agent->name,
            'Cliente: '.($clientName !== '' ? $clientName : 'No identificado'),
            'Teléfono: '.($alerta->phone ?? '—'),
            'Categoría: '.($categoria?->nombre ?? 'Sin categoría'),
            'Número de llamada: '.($alerta->numero_llamada ?? '—'),
            'Paso de llamada: '.($alerta->paso_llamada ?? '—'),
            'Descripción: '.($alerta->descripcion ?? '—'),
            'Fecha y hora: '.optional($alerta->created_at)->timezone('America/Bogota')?->format('Y-m-d H:i'),
        ];

        try {
            Mail::to($emails)->send(new SystemMarkdownMail(
                'Nueva alerta de llamada',
                'Nueva alerta de llamada',
                $bodyLines,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
