<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\TwilioContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Crea y lista plantillas de WhatsApp en Twilio desde el panel (Content API).
 */
class TwilioTemplateController extends Controller
{
    public function index(Agent $agent, TwilioContentService $twilio): JsonResponse
    {
        $this->authorize('view', $agent);

        if (! $twilio->isConfigured()) {
            return response()->json(['configured' => false, 'templates' => []]);
        }

        return response()->json(['configured' => true, 'templates' => $twilio->listTemplates()]);
    }

    public function store(Agent $agent, Request $request, TwilioContentService $twilio): JsonResponse
    {
        $this->authorize('update', $agent);

        if (! $twilio->isConfigured()) {
            return response()->json([
                'error' => 'Twilio no está configurado. Define TWILIO_ACCOUNT_SID y TWILIO_AUTH_TOKEN.',
            ], 422);
        }

        $validated = $request->validate([
            'friendly_name' => 'required|string|max:100',
            'language' => 'nullable|string|max:10',
            'body' => 'required|string|max:1024',
            'category' => 'nullable|in:UTILITY,MARKETING,AUTHENTICATION',
            'variables' => 'nullable|array',
            'variables.*' => 'nullable|string|max:255',
            'submit_whatsapp' => 'boolean',
        ]);

        try {
            $result = $twilio->createTemplate($validated);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['success' => true, 'result' => $result]);
    }
}
