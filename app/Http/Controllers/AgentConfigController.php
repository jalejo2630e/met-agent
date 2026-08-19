<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentApiKey;
use App\Models\AgentClientSourceEndpoint;
use App\Models\AgentDataVariable;
use App\Models\AgentEndpoint;
use App\Services\ElevenLabsSyncService;
use App\Services\N8nSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgentConfigController extends Controller
{
    public function storeEndpoint(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'method' => 'required|in:GET,POST',
            'headers' => 'nullable|array',
            'client_parameter' => 'required|string|max:255',
            'response_mapping' => 'nullable|array',
        ]);

        $agent->endpoints()->create($validated);

        return back()->with('success', 'Endpoint agregado.');
    }

    public function destroyEndpoint(Agent $agent, AgentEndpoint $endpoint)
    {
        $this->authorize('update', $agent);
        if ($endpoint->agent_id !== $agent->id) {
            abort(404);
        }
        $endpoint->delete();

        return back()->with('success', 'Endpoint eliminado.');
    }

    public function updateMessageConfig(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'webhook_url' => 'nullable|url',
            'plantillas' => 'nullable|array',
            'plantillas.*.id' => 'nullable|string|max:255',
            'plantillas.*.name' => 'nullable|string|max:255',
            'plantillas.*.has_variables' => 'nullable|boolean',
            'plantillas.*.variables' => 'nullable|array',
            'plantillas.*.variables.*.name' => 'nullable|string|max:255',
            'plantillas.*.variables.*.source_type' => 'nullable|string|in:field,custom_field,special',
            'plantillas.*.variables.*.source_key' => 'nullable|string|max:255',
            'default_plantilla_id' => 'nullable|string|max:255',
            'schedule_config' => 'nullable|array',
            'schedule_config.hours' => 'nullable|array',
            'schedule_config.hours.start' => 'nullable|string',
            'schedule_config.hours.end' => 'nullable|string',
            'schedule_config.days_of_week' => 'nullable|array',
            'schedule_config.excluded_dates' => 'nullable|array',
            'schedule_config.campaign_times' => 'nullable|array',
            'schedule_config.campaign_times.*' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_config.campaign_slots' => 'nullable|array',
            'schedule_config.campaign_slots.*.time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_config.campaign_slots.*.rule_ids' => 'nullable|array',
            'schedule_config.campaign_slots.*.rule_ids.*' => 'nullable|integer',
            'schedule_config.campaign_slots.*.id_plantilla' => 'nullable|string|max:255',
            'schedule_config.campaign_timezone' => 'nullable|string|max:50',
            'schedule_config.execution_rules' => 'nullable|array',
            'schedule_config.execution_rules.*.field' => 'nullable|string|max:100',
            'schedule_config.execution_rules.*.operator' => 'nullable|string|in:equals,not_equals,in,not_in,gte,lte,gt,lt',
            'schedule_config.execution_rules.*.value' => 'nullable',
            'schedule_config.execution_rules.*.delay_days' => 'nullable|integer|min:0|max:365',
            'schedule_config.execution_rules.*.delay_from' => 'nullable|string|max:100',
        ]);

        if (! $request->user()?->isAdmin()) {
            unset($validated['webhook_url']);
        }

        $agent->messageConfig()->updateOrCreate(
            ['agent_id' => $agent->id],
            $validated
        );

        return back()->with('success', 'Configuración de mensajes actualizada.');
    }

    public function updateCallConfig(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'webhook_url' => 'nullable|url',
            'elevenlabs_agent_id' => 'nullable|string|max:255',
            'elevenlabs_phone_number_id' => 'nullable|string|max:255',
            'schedule_config' => 'nullable|array',
            'schedule_config.hours' => 'nullable|array',
            'schedule_config.hours.start' => 'nullable|string',
            'schedule_config.hours.end' => 'nullable|string',
            'schedule_config.days_of_week' => 'nullable|array',
            'schedule_config.excluded_dates' => 'nullable|array',
            'schedule_config.campaign_times' => 'nullable|array',
            'schedule_config.campaign_times.*' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_config.campaign_slots' => 'nullable|array',
            'schedule_config.campaign_slots.*.time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_config.campaign_slots.*.rule_ids' => 'nullable|array',
            'schedule_config.campaign_slots.*.rule_ids.*' => 'nullable|integer',
            'schedule_config.campaign_timezone' => 'nullable|string|max:50',
            'schedule_config.execution_rules' => 'nullable|array',
            'schedule_config.execution_rules.*.field' => 'nullable|string|max:100',
            'schedule_config.execution_rules.*.operator' => 'nullable|string|in:equals,not_equals,in,not_in,gte,lte,gt,lt',
            'schedule_config.execution_rules.*.value' => 'nullable',
            'schedule_config.execution_rules.*.delay_days' => 'nullable|integer|min:0|max:365',
            'schedule_config.execution_rules.*.delay_from' => 'nullable|string|max:100',
        ]);

        if (! $request->user()?->isAdmin()) {
            unset($validated['webhook_url']);
        }

        $agent->callConfig()->updateOrCreate(
            ['agent_id' => $agent->id],
            $validated
        );

        // El agente de voz de ElevenLabs administra su propio prompt. Aquí solo se
        // guardan el webhook y el ID del agente, que se envían por POST en cada llamada.
        return back()->with('success', 'Configuración de llamadas actualizada.');
    }

    public function storeDataVariable(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:string,number,boolean,json',
            'required' => 'boolean',
        ]);

        $agent->dataVariables()->create($validated);

        return back()->with('success', 'Variable agregada.');
    }

    public function destroyDataVariable(Agent $agent, AgentDataVariable $variable)
    {
        $this->authorize('update', $agent);
        if ($variable->agent_id !== $agent->id) {
            abort(404);
        }
        $variable->delete();

        return back()->with('success', 'Variable eliminada.');
    }

    public function generateApiKey(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate(['name' => 'required|string|max:255']);

        ['api_key' => $apiKey, 'plain_key' => $plainKey] = AgentApiKey::generate($agent->id, $validated['name']);

        return back()->with('success', 'API Key generada. Cópiala ahora (no se mostrará de nuevo): '.$plainKey);
    }

    public function destroyApiKey(Agent $agent, AgentApiKey $apiKey)
    {
        $this->authorize('update', $agent);
        if ($apiKey->agent_id !== $agent->id) {
            abort(404);
        }
        $apiKey->delete();

        return back()->with('success', 'API Key eliminada.');
    }

    public function storeClientField(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'field_name' => 'required|string|max:255',
            'field_type' => 'required|in:string,number,boolean,date,text',
            'required' => 'boolean',
        ]);

        $agent->clientFields()->create($validated);

        return back()->with('success', 'Campo agregado.');
    }

    public function destroyClientField(Agent $agent, \App\Models\AgentClientField $clientField)
    {
        $this->authorize('update', $agent);
        if ($clientField->agent_id !== $agent->id) {
            abort(404);
        }
        $clientField->delete();

        return back()->with('success', 'Campo eliminado.');
    }

    public function storeClientSourceEndpoint(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'headers' => 'nullable|array',
            'schedule_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_timezone' => 'nullable|string|max:50',
        ]);

        $validated['order'] = $agent->clientSourceEndpoints()->max('order') + 1;
        $agent->clientSourceEndpoints()->create($validated);

        return back()->with('success', 'Endpoint de fuente agregado.');
    }

    public function updateClientSourceEndpoint(Request $request, Agent $agent, AgentClientSourceEndpoint $clientSourceEndpoint)
    {
        $this->authorize('update', $agent);
        if ($clientSourceEndpoint->agent_id !== $agent->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'headers' => 'nullable|array',
            'schedule_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_timezone' => 'nullable|string|max:50',
        ]);

        $clientSourceEndpoint->update($validated);

        return back()->with('success', 'Endpoint actualizado.');
    }

    public function destroyClientSourceEndpoint(Agent $agent, AgentClientSourceEndpoint $clientSourceEndpoint)
    {
        $this->authorize('update', $agent);
        if ($clientSourceEndpoint->agent_id !== $agent->id) {
            abort(404);
        }
        $clientSourceEndpoint->delete();

        return back()->with('success', 'Endpoint eliminado.');
    }

    /**
     * Actualiza el prompt del sistema del agente (un solo campo de texto). Es el
     * mismo system prompt (instructions) que usa el agente de texto nativo (WhatsappAgent).
     */
    public function updatePromptConfig(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'system_prompt' => 'required|string|max:20000',
        ]);

        $current = is_array($agent->prompt_configuration) ? $agent->prompt_configuration : [];
        unset($current['sections']);
        $agent->prompt_configuration = array_merge($current, [
            'system_prompt' => trim($validated['system_prompt']),
        ]);
        $agent->save();

        $n8nSynced = false;
        if (app(N8nSyncService::class)->isConfigured() && $agent->n8n_workflow_id && $agent->n8n_prompt_node_id) {
            $n8nSynced = app(N8nSyncService::class)->syncPromptToWorkflow($agent->fresh());
        }

        $message = 'Prompt del sistema actualizado.';
        if ($n8nSynced) {
            $message .= ' Sincronizado con N8N.';
        }

        return back()->with('success', $message);
    }

    /**
     * Actualiza los IDs de integración N8N del agente (workflow y nodo de prompt).
     */
    public function updateN8nConfig(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'n8n_workflow_id' => 'nullable|string|max:255',
            'n8n_prompt_node_id' => 'nullable|string|max:255',
        ]);

        $agent->update([
            'n8n_workflow_id' => $validated['n8n_workflow_id'] ?: null,
            'n8n_prompt_node_id' => $validated['n8n_prompt_node_id'] ?: null,
        ]);

        return back()->with('success', 'Configuración N8N guardada.');
    }
}
