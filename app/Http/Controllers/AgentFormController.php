<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentFormResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentFormController extends Controller
{
    /**
     * Guarda/edita/elimina/reordena las preguntas del formulario del agente
     * en una sola operación (sincronización completa).
     */
    public function updateQuestions(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'questions' => 'present|array',
            'questions.*.id' => 'nullable|integer',
            'questions.*.label' => 'required|string|max:1000',
            'questions.*.help_text' => 'nullable|string|max:1000',
            'questions.*.required' => 'boolean',
        ]);

        DB::transaction(function () use ($agent, $validated) {
            $keepIds = [];

            foreach ($validated['questions'] as $i => $q) {
                $attributes = [
                    'label' => $q['label'],
                    'help_text' => $q['help_text'] ?? null,
                    'required' => $q['required'] ?? false,
                    'order' => $i,
                ];

                // Solo editamos una pregunta existente si pertenece a este agente.
                $existing = ! empty($q['id'])
                    ? $agent->questions()->find($q['id'])
                    : null;

                if ($existing) {
                    $existing->update($attributes);
                    $keepIds[] = $existing->id;
                } else {
                    $keepIds[] = $agent->questions()->create($attributes)->id;
                }
            }

            // Elimina las preguntas que ya no están en la lista enviada.
            $agent->questions()->whereNotIn('id', $keepIds)->delete();
        });

        return back()->with('success', 'Preguntas guardadas.');
    }

    /**
     * Lista los clientes con su enlace único y el estado de su respuesta.
     */
    public function responses(Agent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $responses = $agent->formResponses()
            ->with([
                'client:id,name,lastname,phone,email',
                'answers:id,agent_form_response_id,question_label,answer',
            ])
            ->get()
            ->map(fn (AgentFormResponse $r) => [
                'id' => $r->id,
                'token' => $r->token,
                'url' => route('public.form.show', $r->token),
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'client' => $r->client ? [
                    'id' => $r->client->id,
                    'name' => $r->client->name,
                    'lastname' => $r->client->lastname,
                    'phone' => $r->client->phone,
                    'email' => $r->client->email,
                ] : null,
                'answers' => $r->answers->map(fn ($a) => [
                    'question_label' => $a->question_label,
                    'answer' => $a->answer,
                ]),
            ]);

        $clientsWithoutLink = $agent->clients()
            ->whereDoesntHave('formResponse')
            ->count();

        return response()->json([
            'responses' => $responses,
            'clients_without_link' => $clientsWithoutLink,
        ]);
    }

    /**
     * Crea un enlace (token) para cada cliente del agente que aún no tenga uno.
     */
    public function generateLinks(Agent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $created = 0;

        $agent->clients()
            ->whereDoesntHave('formResponse')
            ->select('id')
            ->chunkById(500, function ($clients) use ($agent, &$created) {
                foreach ($clients as $client) {
                    $agent->formResponses()->create([
                        'client_id' => $client->id,
                        'token' => AgentFormResponse::generateToken(),
                    ]);
                    $created++;
                }
            });

        return response()->json([
            'created' => $created,
            'message' => $created > 0
                ? "Se generaron {$created} enlace(s)."
                : 'Todos los clientes ya tienen enlace.',
        ]);
    }

    /**
     * Regenera el token de un cliente (invalida el enlace anterior).
     */
    public function regenerate(Agent $agent, int $response): JsonResponse
    {
        $this->authorize('update', $agent);

        $formResponse = $agent->formResponses()->findOrFail($response);
        $formResponse->update(['token' => AgentFormResponse::generateToken()]);

        return response()->json([
            'token' => $formResponse->token,
            'url' => route('public.form.show', $formResponse->token),
        ]);
    }
}
