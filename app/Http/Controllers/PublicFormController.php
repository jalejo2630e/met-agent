<?php

namespace App\Http\Controllers;

use App\Models\AgentFormResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    /**
     * Muestra el formulario público asociado al token del cliente.
     */
    public function show(string $token): Response
    {
        $formResponse = AgentFormResponse::where('token', $token)
            ->with(['agent:id,name', 'client:id,name,lastname', 'answers'])
            ->firstOrFail();

        $questions = $formResponse->agent->questions()
            ->get(['id', 'label', 'help_text', 'required', 'order']);

        $answers = $formResponse->answers
            ->pluck('answer', 'agent_question_id');

        return Inertia::render('Public/Form', [
            'token' => $token,
            'agentName' => $formResponse->agent->name,
            'clientName' => trim(($formResponse->client->name ?? '').' '.($formResponse->client->lastname ?? '')),
            'questions' => $questions,
            'answers' => $answers,
            'submittedAt' => $formResponse->submitted_at?->toIso8601String(),
        ]);
    }

    /**
     * Recibe y guarda las respuestas del cliente.
     */
    public function submit(Request $request, string $token)
    {
        $formResponse = AgentFormResponse::where('token', $token)
            ->with('agent')
            ->firstOrFail();

        $questions = $formResponse->agent->questions()->get();

        $rules = [];
        $attributes = [];
        foreach ($questions as $question) {
            $key = "answers.{$question->id}";
            $rules[$key] = ($question->required ? 'required' : 'nullable').'|string|max:5000';
            $attributes[$key] = 'la respuesta a «'.$question->label.'»';
        }

        $request->validate($rules, [], $attributes);

        DB::transaction(function () use ($formResponse, $questions, $request) {
            foreach ($questions as $question) {
                $formResponse->answers()->updateOrCreate(
                    ['agent_question_id' => $question->id],
                    [
                        'question_label' => $question->label,
                        'answer' => $request->input("answers.{$question->id}"),
                    ]
                );
            }

            $formResponse->forceFill(['submitted_at' => now()])->save();
        });

        return redirect()
            ->route('public.form.show', $token)
            ->with('success', '¡Gracias! Tus respuestas se enviaron correctamente.');
    }
}
