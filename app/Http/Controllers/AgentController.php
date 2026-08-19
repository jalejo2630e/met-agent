<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentEndpointLog;
use App\Models\ClientContactLog;
use App\Models\ContactQueue;
use App\Observers\AgentObserver;
use App\Services\AgentPromptBuilder;
use App\Services\CallCountService;
use App\Services\ClientListFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Agent::class);

        $valid = [Agent::STATUS_ACTIVE, Agent::STATUS_DRAFT, Agent::STATUS_INACTIVE];

        // Estados a mostrar. Por defecto: activos y borradores (los cancelados quedan ocultos
        // hasta que se seleccionen desde el filtro).
        $statuses = $request->input('statuses');
        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }
        $statuses = is_array($statuses) ? array_values(array_intersect($statuses, $valid)) : [];
        if ($statuses === []) {
            $statuses = [Agent::STATUS_ACTIVE, Agent::STATUS_DRAFT];
        }

        $agents = Agent::query()
            ->with('user:id,name')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->whereIn('status', $statuses)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
            'filters' => [
                'search' => $request->search,
                'statuses' => $statuses,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Agent::class);

        return Inertia::render('Agents/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Agent::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,draft,inactive',
            'prompt_configuration' => 'required|array',
            'prompt_configuration.sections' => 'required|array',
            'prompt_configuration.sections.greeting' => 'nullable|string|max:2000',
            'prompt_configuration.sections.behavior' => 'nullable|string|max:4000',
            'prompt_configuration.sections.business_rules' => 'nullable|string|max:4000',
            'prompt_configuration.sections.additional' => 'nullable|string|max:4000',
            'prompt_configuration.sections.tools' => 'nullable|array',
            'prompt_configuration.sections.tools.*.name' => 'nullable|string|max:255',
            'prompt_configuration.sections.tools.*.description' => 'nullable|string|max:1000',
        ]);

        $sections = $this->normalizePromptSections($validated['prompt_configuration']['sections']);
        $systemPrompt = AgentPromptBuilder::buildFromSections($sections);
        $promptConfiguration = array_merge($validated['prompt_configuration'], [
            'sections' => $sections,
            'system_prompt' => $systemPrompt,
        ]);

        try {
            DB::beginTransaction();

            $agent = Agent::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'prompt_configuration' => $promptConfiguration,
                'status' => $validated['status'],
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            $tableName = \App\Support\WhatsappConversationsConnection::readsViaRest()
                ? \App\Support\WhatsappConversationsConnection::supabaseTableNameForAgent($agent->id)
                : AgentObserver::getTableName($agent->id);

            $success = \App\Support\WhatsappConversationsConnection::managesLocalTables()
                ? "Agente creado exitosamente. Tabla de conversaciones creada: {$tableName}"
                : "Agente creado exitosamente. Conversaciones WhatsApp en Supabase (tabla esperada: {$tableName}).";

            return redirect()
                ->route('agents.show', $agent)
                ->with('success', $success);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($agent)) {
                $agent->delete();
            }

            return back()
                ->withErrors(['error' => 'Error al crear el agente: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function show(Request $request, Agent $agent): Response
    {
        $this->authorize('view', $agent);

        $agent->load([
            'user:id,name',
            'endpoints',
            'messageConfig',
            'callConfig',
            'dataVariables',
            'apiKeys',
            'clientFields',
            'clientSourceEndpoints',
            'questions',
            'reportWidgets',
        ]);

        $endpointLogs = AgentEndpointLog::whereHas('endpoint', fn ($q) => $q->where('agent_id', $agent->id))
            ->with('endpoint:id,name,url')
            ->latest()
            ->limit(100)
            ->get();

        $contactLogs = ClientContactLog::where('agent_id', $agent->id)
            ->with('client:id,name,lastname,phone')
            ->latest('contacted_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'channel' => $log->channel,
                'contacted_at' => $log->contacted_at?->toIso8601String(),
                'client' => $log->client ? ['name' => $log->client->name, 'lastname' => $log->client->lastname, 'phone' => $log->client->phone] : null,
            ]);

        $queueRuns = ContactQueue::where('agent_id', $agent->id)
            ->whereIn('status', [ContactQueue::STATUS_PROCESSING, ContactQueue::STATUS_COMPLETED])
            ->whereNotNull('last_run_at')
            ->orderByDesc('last_run_at')
            ->limit(30)
            ->get(['id', 'type', 'status', 'last_run_at', 'processed_count', 'failed_count', 'client_ids'])
            ->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'status' => $q->status,
                'last_run_at' => $q->last_run_at?->toIso8601String(),
                'processed_count' => $q->processed_count ?? 0,
                'failed_count' => $q->failed_count ?? 0,
                'total' => count($q->client_ids ?? []),
            ]);

        $clientsQuery = $agent->clients()->withCount(['contactLogs', 'loadDates', 'alertasLlamada'])->latest();
        app(ClientListFilterService::class)->apply($request, $clientsQuery);

        $clients = $clientsQuery->paginate(20)->withQueryString();
        app(CallCountService::class)->attachCallCounts($clients->getCollection());

        return Inertia::render('Agents/Show', [
            'agent' => $agent,
            'endpointLogs' => $endpointLogs,
            'contactLogs' => $contactLogs,
            'queueRuns' => $queueRuns,
            'clients' => $clients,
            'filters' => $request->only(['date', 'search', 'status']),
            'collectDataUrl' => url("/api/agents/{$agent->id}/collect-data"),
        ]);
    }

    public function edit(Agent $agent): Response
    {
        $this->authorize('update', $agent);

        return Inertia::render('Agents/Edit', [
            'agent' => $agent,
        ]);
    }

    public function update(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,draft,inactive',
            'prompt_configuration' => 'required|array',
            'prompt_configuration.sections' => 'required|array',
            'prompt_configuration.sections.greeting' => 'nullable|string|max:2000',
            'prompt_configuration.sections.behavior' => 'nullable|string|max:4000',
            'prompt_configuration.sections.business_rules' => 'nullable|string|max:4000',
            'prompt_configuration.sections.additional' => 'nullable|string|max:4000',
            'prompt_configuration.sections.tools' => 'nullable|array',
            'prompt_configuration.sections.tools.*.name' => 'nullable|string|max:255',
            'prompt_configuration.sections.tools.*.description' => 'nullable|string|max:1000',
        ]);

        $sections = $this->normalizePromptSections($validated['prompt_configuration']['sections']);
        $systemPrompt = AgentPromptBuilder::buildFromSections($sections);
        $promptConfiguration = array_merge($agent->prompt_configuration ?? [], [
            'sections' => $sections,
            'system_prompt' => $systemPrompt,
        ]);

        try {
            DB::beginTransaction();

            $agent->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'status' => $validated['status'],
                'prompt_configuration' => $promptConfiguration,
            ]);

            DB::commit();

            return back()->with('success', 'Agente actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Error al actualizar: '.$e->getMessage()]);
        }
    }

    public function destroy(Agent $agent)
    {
        $this->authorize('delete', $agent);

        try {
            DB::beginTransaction();

            $agent->delete();

            DB::commit();

            return redirect()
                ->route('agents.index')
                ->with('success', 'Agente eliminado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Error al eliminar: '.$e->getMessage()]);
        }
    }

    /**
     * Normaliza el array de secciones del prompt (mismo formato que AgentConfigController::updatePromptConfig).
     */
    private function normalizePromptSections(array $raw): array
    {
        return [
            'greeting' => trim((string) ($raw['greeting'] ?? '')),
            'behavior' => trim((string) ($raw['behavior'] ?? '')),
            'business_rules' => trim((string) ($raw['business_rules'] ?? '')),
            'additional' => trim((string) ($raw['additional'] ?? '')),
            'tools' => array_values(array_map(function ($t) {
                return [
                    'name' => trim((string) ($t['name'] ?? '')),
                    'description' => trim((string) ($t['description'] ?? '')),
                ];
            }, $raw['tools'] ?? [])),
        ];
    }
}
