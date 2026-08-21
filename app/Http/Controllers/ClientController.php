<?php

namespace App\Http\Controllers;

use App\Exports\ClientTemplateExport;
use App\Imports\ClientsImport;
use App\Jobs\ProcessContactQueueJob;
use App\Models\Agent;
use App\Models\AgentFormResponse;
use App\Models\AgentWhatsappConversation;
use App\Models\AlertaCategoria;
use App\Models\AlertaLlamada;
use App\Models\Client;
use App\Models\ClientContactLog;
use App\Models\ClientLoadDate;
use App\Models\ClientNote;
use App\Models\ContactQueue;
use App\Models\RegistroAudioLlamada;
use App\Models\RegistroEscritoLlamada;
use App\Services\CallCountService;
use App\Services\ClientListFilterService;
use App\Services\ContactQueueService;
use App\Services\CustomReportBuilderService;
use App\Services\SupabaseCallAudioRestService;
use App\Services\SupabaseCallTranscriptsRestService;
use App\Services\SupabaseWhatsappMessagesRestService;
use App\Support\CallTranscriptsConnection;
use App\Support\WhatsappConversationsConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index(Request $request, Agent $agent): Response
    {
        $this->authorize('view', $agent);

        $clientsQuery = $agent->clients()->withCount(['contactLogs', 'loadDates', 'alertasLlamada'])->latest();
        app(ClientListFilterService::class)->apply($request, $clientsQuery);

        $clients = $clientsQuery->paginate(20)->withQueryString();
        app(CallCountService::class)->attachCallCounts($clients->getCollection());
        $agent->load(['clientFields', 'callConfig', 'messageConfig']);

        return Inertia::render('Agents/Clients/Index', [
            'agent' => $agent,
            'clients' => $clients,
            'filters' => $request->only(['date', 'search', 'status']),
        ]);
    }

    public function listForCampaign(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $query = $agent->clients()->orderBy('name');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('lastname', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }
        $clients = $query->limit(500)->get(['id', 'name', 'lastname', 'phone', 'email']);

        return response()->json([
            'clients' => $clients->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'lastname' => $c->lastname,
                'phone' => $c->phone,
                'email' => $c->email,
            ]),
        ]);
    }

    public function clientsByRules(Request $request, Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.field' => 'required|string|max:100',
            'rules.*.operator' => 'required|string|in:equals,not_equals,in,not_in,gte,lte,gt,lt',
            'rules.*.value' => 'nullable',
            'rules.*.delay_days' => 'nullable|integer|min:0|max:365',
            'rules.*.delay_from' => 'nullable|string|max:100',
        ]);

        $rules = $validated['rules'];
        $rules = array_map(function ($r) {
            $value = $r['value'] ?? null;
            if (in_array($r['operator'], ['in', 'not_in'], true) && is_string($value)) {
                $value = array_map('trim', explode(',', $value));
            }

            $rule = [
                'field' => $r['field'],
                'operator' => $r['operator'],
                'value' => $value,
            ];
            if (isset($r['delay_days']) && (int) $r['delay_days'] > 0) {
                $rule['delay_days'] = (int) $r['delay_days'];
                $rule['delay_from'] = $r['delay_from'] ?? 'last_contacted_at';
            }

            return $rule;
        }, $rules);

        $clients = $agent->clients()
            ->withCount('contactLogs')
            ->withMax('contactLogs as last_contacted_at', 'contacted_at')
            ->limit(5000)
            ->get();
        $service = app(\App\Services\ContactQueueService::class);
        $filtered = $service->filterClientsByExecutionRules($clients, ['execution_rules' => $rules], null);
        $clientIds = $filtered->pluck('id')->values()->all();

        return response()->json([
            'client_ids' => $clientIds,
            'count' => count($clientIds),
        ]);
    }

    public function store(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $rules = [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'document_type' => 'nullable|string|max:50',
            'document' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'custom_fields' => 'nullable|array',
        ];

        foreach ($agent->clientFields as $field) {
            $rules['custom_fields.'.$field->field_name] = $field->required ? 'required' : 'nullable';
        }

        $validated = $request->validate($rules);

        $customFields = $validated['custom_fields'] ?? [];
        unset($validated['custom_fields']);

        $status = $validated['status'] ?? Client::STATUS_NO_CONTACTADO;
        if ($status === '') {
            $status = Client::STATUS_NO_CONTACTADO;
        }
        unset($validated['status']);

        $now = now();
        $client = $agent->clients()->create(array_merge($validated, [
            'custom_fields' => $customFields ?: null,
            'loaded_at' => $now,
            'status' => $status,
        ]));
        ClientLoadDate::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'loaded_at' => $now,
            'source' => ClientLoadDate::SOURCE_MANUAL,
        ]);

        return back()->with('success', 'Cliente creado.');
    }

    public function update(Request $request, Agent $agent, Client $client)
    {
        $this->authorize('update', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $rules = [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'document_type' => 'nullable|string|max:50',
            'document' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'custom_fields' => 'nullable|array',
        ];

        foreach ($agent->clientFields as $field) {
            $rules['custom_fields.'.$field->field_name] = $field->required ? 'required' : 'nullable';
        }

        $validated = $request->validate($rules);

        $customFields = $validated['custom_fields'] ?? [];
        unset($validated['custom_fields']);

        $status = $validated['status'] ?? null;
        unset($validated['status']);

        $updateData = array_merge($validated, ['custom_fields' => $customFields ?: null]);
        $updateData['status'] = ($status !== null && $status !== '') ? $status : Client::STATUS_NO_CONTACTADO;
        $client->update($updateData);

        return back()->with('success', 'Cliente actualizado.');
    }

    /**
     * Detalle del cliente para el modal: datos, avance por temas, tracking de
     * contactos y notas. Las llamadas/transcripciones se cargan aparte con los
     * endpoints existentes (call-transcript / call-audio).
     */
    public function detail(Agent $agent, Client $client, CustomReportBuilderService $reports): JsonResponse
    {
        $this->authorize('view', $agent);
        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        // Temas: usa la config del reporte topic_progress del agente si existe;
        // si no hay ninguno configurado, no se calculan temas.
        $topicWidget = $agent->reportWidgets()
            ->where('metric', \App\Models\AgentReportWidget::METRIC_TOPIC_PROGRESS)
            ->first();
        $rawTopics = $topicWidget?->config['topics'] ?? [];
        $topics = $reports->normalizeTopics($rawTopics);
        $progress = $topics !== []
            ? $reports->evaluateClientTopics($client->custom_fields ?? [], $topics)
            : null;

        $contactLogs = $client->contactLogs()
            ->orderByDesc('contacted_at')
            ->limit(100)
            ->get(['channel', 'contacted_at'])
            ->map(fn ($l) => [
                'channel' => $l->channel,
                'contacted_at' => $l->contacted_at?->toIso8601String(),
            ]);

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'lastname' => $client->lastname,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status,
                'document_type' => $client->document_type,
                'document' => $client->document,
                'custom_fields' => $client->custom_fields ?? [],
            ],
            'progress' => $progress,
            'contact_logs' => $contactLogs,
            'notes' => $this->notesPayload($client),
            'call_alerts' => $this->callAlertsPayload($client),
            'alert_categories' => AlertaCategoria::orderBy('nombre')->get(['id', 'nombre', 'color']),
        ]);
    }

    public function storeNote(Request $request, Agent $agent, Client $client): JsonResponse
    {
        $this->authorize('view', $agent);
        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $client->notes()->create([
            'agent_id' => $agent->id,
            'user_id' => $request->user()?->id,
            'body' => $validated['body'],
        ]);

        return response()->json(['notes' => $this->notesPayload($client)]);
    }

    public function destroyNote(Agent $agent, Client $client, ClientNote $note): JsonResponse
    {
        $this->authorize('view', $agent);
        if ($client->agent_id !== $agent->id || $note->client_id !== $client->id) {
            abort(404);
        }

        $note->delete();

        return response()->json(['notes' => $this->notesPayload($client)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notesPayload(Client $client): array
    {
        return $client->notes()->with('user:id,name')->get()
            ->map(fn (ClientNote $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->user?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Lista las alertas de llamada de un cliente (para el popup en la tabla de clientes).
     */
    public function callAlerts(Agent $agent, Client $client): JsonResponse
    {
        $this->authorize('view', $agent);
        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        return response()->json([
            'call_alerts' => $this->callAlertsPayload($client),
            'alert_categories' => AlertaCategoria::orderBy('nombre')->get(['id', 'nombre', 'color']),
        ]);
    }

    /**
     * Reclasifica una alerta de llamada (asigna o quita la categoría).
     */
    public function updateCallAlert(Request $request, Agent $agent, Client $client, AlertaLlamada $alerta): JsonResponse
    {
        $this->authorize('view', $agent);
        if ($client->agent_id !== $agent->id || $alerta->client_id !== $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'alerta_categoria_id' => ['nullable', 'integer', 'exists:alerta_categorias,id'],
        ]);

        $alerta->update(['alerta_categoria_id' => $validated['alerta_categoria_id'] ?? null]);

        return response()->json(['call_alerts' => $this->callAlertsPayload($client)]);
    }

    public function destroyCallAlert(Agent $agent, Client $client, AlertaLlamada $alerta): JsonResponse
    {
        $this->authorize('view', $agent);
        if ($client->agent_id !== $agent->id || $alerta->client_id !== $client->id) {
            abort(404);
        }

        $alerta->delete();

        return response()->json(['call_alerts' => $this->callAlertsPayload($client)]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function callAlertsPayload(Client $client): array
    {
        return $client->alertasLlamada()->with('categoria:id,nombre,color')->get()
            ->map(fn (AlertaLlamada $a) => [
                'id' => $a->id,
                'phone' => $a->phone,
                'numero_llamada' => $a->numero_llamada,
                'paso_llamada' => $a->paso_llamada,
                'descripcion' => $a->descripcion,
                'categoria' => $a->categoria ? [
                    'id' => $a->categoria->id,
                    'nombre' => $a->categoria->nombre,
                    'color' => $a->categoria->color,
                ] : null,
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function whatsappMessages(Agent $agent, Client $client)
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $phone = $client->phone;
        if (! $phone) {
            return response()->json(['messages' => []]);
        }

        $messages = [];
        $note = null;

        if (WhatsappConversationsConnection::readsViaRest()) {
            try {
                $rows = app(SupabaseWhatsappMessagesRestService::class)->fetchRows($agent, $phone);
                foreach ($rows as $row) {
                    $msg = $row['message'] ?? null;
                    if (! is_array($msg) && is_string($msg)) {
                        $decoded = json_decode($msg, true);
                        $msg = is_array($decoded) ? $decoded : null;
                    }
                    if (is_array($msg) && isset($msg['type'], $msg['content'])) {
                        $messages[] = [
                            'type' => $msg['type'],
                            'content' => $msg['content'],
                            'created_at' => isset($row['created_at']) ? Carbon::parse($row['created_at'])->toIso8601String() : null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $note = 'No se pudo cargar el historial desde Supabase. Revisa URL y clave (VITE_SUPABASE_URL, VITE_SUPABASE_ANON_KEY o VITE_SUPABASE_KEY), nombre de tabla, columna de teléfono (WHATSAPP_SUPABASE_PHONE_COLUMN / Ajustes) y políticas RLS.';
            }
        } else {
            $rows = AgentWhatsappConversation::forAgent($agent)
                ->where('session_id', $phone)
                ->orderBy('id', 'asc')
                ->get(['id', 'message', 'created_at']);

            foreach ($rows as $row) {
                $msg = $row->message;
                if (is_array($msg) && isset($msg['type'], $msg['content'])) {
                    $messages[] = [
                        'type' => $msg['type'],
                        'content' => $msg['content'],
                        'created_at' => $row->created_at?->toIso8601String(),
                    ];
                }
            }
        }

        // Incluir también los mensajes atendidos por el agente nativo vía Twilio.
        foreach ($this->twilioMessagesForClient($agent, $phone) as $m) {
            $messages[] = $m;
        }

        usort($messages, fn ($a, $b) => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));

        $response = ['messages' => $messages];
        if ($note !== null) {
            $response['message'] = $note;
        }

        return response()->json($response);
    }

    /**
     * Mensajes de WhatsApp/SMS atendidos por el agente nativo (tabla twilio_messages),
     * mapeados al formato del historial del cliente (type: ai = empresa, human = usuario).
     *
     * @return array<int, array{type: string, content: string, created_at: ?string}>
     */
    private function twilioMessagesForClient(Agent $agent, string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return [];
        }
        $last10 = substr($digits, -10);

        return \App\Models\TwilioMessage::where('agent_id', $agent->id)
            ->where(function ($q) use ($phone, $digits, $last10) {
                $q->where('from_number', $phone)
                    ->orWhere('from_number', $digits)
                    ->orWhere('from_number', 'like', '%'.$last10);
            })
            ->orderBy('id', 'asc')
            ->get(['direction', 'body', 'created_at'])
            ->map(fn ($row) => [
                'type' => $row->direction === 'outbound' ? 'ai' : 'human',
                'content' => (string) $row->body,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function callTranscript(Request $request, Agent $agent, Client $client): JsonResponse
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $phone = $client->phone;
        if ($request->filled('phone')) {
            $phone = trim((string) $request->query('phone'));
        }
        if (! $phone) {
            return $this->callTranscriptJson(['calls' => [], 'message' => 'Cliente sin teléfono']);
        }

        $phoneDigits = preg_replace('/\D/', '', $phone);
        if ($phoneDigits === '') {
            return $this->callTranscriptJson(['calls' => [], 'message' => 'Teléfono inválido']);
        }

        $callId = $request->integer('call_id');

        $campanaQuery = $request->query('campana');
        $campanaQuery = ($campanaQuery !== null && $campanaQuery !== '') ? trim((string) $campanaQuery) : null;

        if (CallTranscriptsConnection::usesRest()) {
            return $this->callTranscriptViaSupabaseRest($agent, $phoneDigits, $callId, $campanaQuery);
        }

        $campanaEloquent = $campanaQuery ?? config('services.campana_ainoa');
        $campanaEloquent = is_string($campanaEloquent) ? trim($campanaEloquent) : '';

        // Cargar una llamada concreta (transcripción + audio)
        if ($callId > 0) {
            $registro = RegistroEscritoLlamada::query()
                ->forCampanaValue($campanaEloquent !== '' ? $campanaEloquent : null)
                ->where('agent_id', (string) $agent->id)
                ->where('id', $callId)
                ->first();
            if (! $registro) {
                return $this->callTranscriptJson(['call' => null, 'message' => 'Llamada no encontrada']);
            }
            $storedDigits = preg_replace('/\D/', '', (string) $registro->phone);
            if ($storedDigits !== $phoneDigits && ! str_ends_with($storedDigits, $phoneDigits) && ! str_ends_with($phoneDigits, $storedDigits)) {
                return $this->callTranscriptJson(['call' => null, 'message' => 'Llamada no pertenece a este cliente']);
            }

            $transcript = $this->normalizeTranscriptForCallModal(
                is_string($registro->transcript) ? $registro->transcript : null
            );
            $variablesExtraidas = $registro->variables_extraidas;
            if (is_string($variablesExtraidas)) {
                $decoded = json_decode($variablesExtraidas, true);
                $variablesExtraidas = is_array($decoded) ? $decoded : null;
            }
            $audio = null;
            if ($registro->conversation_id) {
                $audioRow = RegistroAudioLlamada::where('conversation_id', $registro->conversation_id)
                    ->whereNotNull('audio')->where('audio', '!=', '')->first(['audio']);
                $audio = $audioRow?->audio;
            }

            return $this->callTranscriptJson([
                'call' => [
                    'id' => $registro->id,
                    'created_at' => $registro->created_at?->toIso8601String(),
                    'duration_call_seg' => $registro->duration_call_seg,
                    'conversation_id' => $registro->conversation_id,
                    'transcript' => $transcript,
                    'variables_extraidas' => $variablesExtraidas,
                    'audio' => $audio,
                ],
            ]);
        }

        // Solo lista de fechas (id, created_at, duration, has_audio) sin precargar transcripción ni audio
        $registros = RegistroEscritoLlamada::query()
            ->forCampanaValue($campanaEloquent !== '' ? $campanaEloquent : null)
            ->where('agent_id', (string) $agent->id)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get(['id', 'phone', 'conversation_id', 'created_at', 'duration_call_seg']);

        $registros = $registros->filter(function ($r) use ($phoneDigits) {
            $storedDigits = preg_replace('/\D/', '', (string) $r->phone);

            return $storedDigits === $phoneDigits
                || str_ends_with($storedDigits, $phoneDigits)
                || str_ends_with($phoneDigits, $storedDigits);
        })->values();

        if ($registros->isEmpty()) {
            return $this->callTranscriptJson(['calls' => [], 'message' => 'No hay llamadas registradas para este cliente']);
        }

        $conversationIdsWithAudio = RegistroAudioLlamada::whereIn('conversation_id', $registros->pluck('conversation_id')->filter()->unique())
            ->whereNotNull('audio')->where('audio', '!=', '')
            ->pluck('conversation_id')
            ->all();

        $calls = [];
        foreach ($registros as $registro) {
            $convId = $registro->conversation_id;
            $calls[] = [
                'id' => $registro->id,
                'created_at' => $registro->created_at?->toIso8601String(),
                'duration_call_seg' => $registro->duration_call_seg,
                'conversation_id' => $convId,
                'has_audio' => $convId && in_array($convId, $conversationIdsWithAudio, true),
            ];
        }

        return $this->callTranscriptJson(['calls' => $calls]);
    }

    /**
     * Audio de la llamada (base64) por conversation_id; lectura desde BD local o Supabase REST.
     */
    public function callAudio(Request $request, Agent $agent, Client $client): JsonResponse
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $conversationId = trim((string) $request->query('conversation_id'));
        if ($conversationId === '') {
            return response()->json(['message' => 'Parámetro conversation_id requerido', 'audio' => null], 422);
        }

        $phone = $client->phone;
        if ($request->filled('phone')) {
            $phone = trim((string) $request->query('phone'));
        }
        $phoneDigits = preg_replace('/\D/', '', (string) $phone);
        if ($phoneDigits === '') {
            return response()->json(['message' => 'Teléfono inválido', 'audio' => null], 422);
        }

        $campanaQuery = $request->query('campana');
        $campanaQuery = ($campanaQuery !== null && $campanaQuery !== '') ? trim((string) $campanaQuery) : null;

        if (! $this->clientOwnsConversationForTranscript($agent, $phoneDigits, $campanaQuery, $conversationId)) {
            return response()->json(['message' => 'Audio no disponible para este cliente', 'audio' => null], 403);
        }

        $audio = null;
        if (CallTranscriptsConnection::usesRest()) {
            try {
                $audio = app(SupabaseCallAudioRestService::class)->findAudioBase64($conversationId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // El webhook post-call nativo de ElevenLabs siempre guarda el audio en la
        // BD local (registro_audio_llamadas) en un POST aparte, relacionado por
        // conversation_id. Se usa como fuente principal (o fallback si el origen
        // configurado es Supabase pero no lo tiene).
        if ($audio === null || $audio === '') {
            $row = RegistroAudioLlamada::where('conversation_id', $conversationId)
                ->whereNotNull('audio')->where('audio', '!=', '')
                ->first(['audio']);
            $audio = $row?->audio;
        }

        if ($audio === null || $audio === '') {
            return response()->json(['message' => 'No hay audio para esta conversación', 'audio' => null]);
        }

        return response()->json(['audio' => $audio]);
    }

    public function indexContactQueues(Request $request, Agent $agent)
    {
        $this->authorize('view', $agent);

        $status = $request->get('status');
        $query = ContactQueue::where('agent_id', $agent->id)->orderByDesc('created_at');

        if ($status && in_array($status, ['pending', 'processing', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $queues = $query->limit(100)->get();

        return response()->json([
            'contact_queues' => $queues->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'status' => $q->status,
                'client_count' => $q->usesRulesForSelection() ? null : count($q->client_ids ?? []),
                'by_rules' => $q->usesRulesForSelection(),
                'processed_count' => $q->processed_count ?? 0,
                'failed_count' => $q->failed_count ?? 0,
                'next_run_at' => $q->next_run_at?->toIso8601String(),
                'last_run_at' => $q->last_run_at?->toIso8601String(),
                'created_at' => $q->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function cancelContactQueue(Request $request, Agent $agent, ContactQueue $contactQueue)
    {
        $this->authorize('view', $agent);

        if ($contactQueue->agent_id !== $agent->id) {
            abort(404);
        }

        if (! in_array($contactQueue->status, [ContactQueue::STATUS_PENDING, ContactQueue::STATUS_PROCESSING], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden cancelar campañas pendientes o en proceso.',
            ], 422);
        }

        $contactQueue->update(['status' => ContactQueue::STATUS_CANCELLED]);

        return response()->json([
            'success' => true,
            'message' => 'Campaña cancelada correctamente.',
        ]);
    }

    public function storeContactQueue(Request $request, Agent $agent)
    {
        $this->authorize('view', $agent);

        $validated = $request->validate([
            'type' => 'required|in:call,whatsapp',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'integer',
            'client_selection_rules' => 'nullable|array',
            'client_selection_rules.*.field' => 'required_with:client_selection_rules|string|max:100',
            'client_selection_rules.*.operator' => 'required_with:client_selection_rules|string|in:equals,not_equals,in,not_in,gte,lte,gt,lt',
            'client_selection_rules.*.value' => 'nullable',
            'client_selection_rules.*.delay_days' => 'nullable|integer|min:0|max:365',
            'client_selection_rules.*.delay_from' => 'nullable|string|max:100',
            'immediate' => 'boolean',
            'id_plantilla' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|date|after_or_equal:today',
            'scheduled_time' => 'nullable|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'schedule_config' => 'nullable|array',
            'schedule_config.days_of_week' => 'nullable|array',
            'schedule_config.excluded_dates' => 'nullable|array',
            'schedule_config.campaign_slots' => 'nullable|array',
            'schedule_config.campaign_slots.*.time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'schedule_config.campaign_slots.*.rule_ids' => 'nullable|array',
            'schedule_config.campaign_slots.*.id_plantilla' => 'nullable|string|max:255',
            'schedule_config.campaign_timezone' => 'nullable|string|max:50',
        ]);

        $type = $validated['type'];
        $immediate = (bool) ($validated['immediate'] ?? false);
        $scheduledDate = $validated['scheduled_date'] ?? null;
        $scheduledTime = $validated['scheduled_time'] ?? null;
        $idPlantilla = $validated['id_plantilla'] ?? null;
        if ($idPlantilla === '') {
            $idPlantilla = null;
        }

        $clientSelectionRules = $validated['client_selection_rules'] ?? null;
        $clientIds = array_values(array_unique(array_map('intval', $validated['client_ids'] ?? [])));

        if (! empty($clientSelectionRules)) {
            $rules = array_map(function ($r) {
                $value = $r['value'] ?? null;
                if (in_array($r['operator'] ?? '', ['in', 'not_in'], true) && is_string($value)) {
                    $value = array_map('trim', explode(',', $value));
                }
                $rule = [
                    'field' => $r['field'],
                    'operator' => $r['operator'] ?? 'equals',
                    'value' => $value,
                ];
                if (isset($r['delay_days']) && (int) $r['delay_days'] > 0) {
                    $rule['delay_days'] = (int) $r['delay_days'];
                    $rule['delay_from'] = $r['delay_from'] ?? 'last_contacted_at';
                }

                return $rule;
            }, array_filter($clientSelectionRules, fn ($r) => ! empty($r['field'])));

            if (empty($rules)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las reglas de selección deben tener al menos un campo definido.',
                ], 422);
            }
            $clientIds = [];
        } else {
            $rules = null;
            $clients = Client::where('agent_id', $agent->id)->whereIn('id', $clientIds)->pluck('id')->toArray();
            if (empty($clients)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selecciona clientes manualmente o define reglas de selección.',
                ], 422);
            }
            $clientIds = $clients;

            if ($type === ContactQueue::TYPE_WHATSAPP) {
                $withPhone = Client::where('agent_id', $agent->id)
                    ->whereIn('id', $clientIds)
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->pluck('id')
                    ->toArray();
                $clientIds = $withPhone;
                if (empty($clientIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ningún cliente seleccionado tiene teléfono registrado.',
                    ], 422);
                }
            }
        }

        $config = $type === 'call' ? $agent->callConfig : $agent->messageConfig;
        $scheduleConfigInput = $validated['schedule_config'] ?? null;

        if ($scheduleConfigInput) {
            $existing = $config?->schedule_config ?? [];
            $slots = array_values(array_filter($scheduleConfigInput['campaign_slots'] ?? [], fn ($s) => ! empty($s['time'])));
            foreach ($slots as &$slot) {
                $slot['rule_ids'] = $slot['rule_ids'] ?? [];
                if ($type !== ContactQueue::TYPE_WHATSAPP) {
                    unset($slot['id_plantilla']);
                }
            }
            $scheduleConfig = array_merge($existing, [
                'days_of_week' => $scheduleConfigInput['days_of_week'] ?? [],
                'excluded_dates' => $scheduleConfigInput['excluded_dates'] ?? [],
                'campaign_slots' => $slots,
                'campaign_timezone' => $scheduleConfigInput['campaign_timezone'] ?? 'America/Bogota',
            ]);
            $configModel = $type === 'call' ? $agent->callConfig() : $agent->messageConfig();
            $configModel->updateOrCreate(
                ['agent_id' => $agent->id],
                ['schedule_config' => $scheduleConfig]
            );
            $agent->load($type === 'call' ? 'callConfig' : 'messageConfig');
            $config = $type === 'call' ? $agent->callConfig : $agent->messageConfig;
        }

        $webhookUrl = $config?->webhook_url ?? null;
        if (! $webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => $type === 'call'
                    ? 'No hay webhook de llamadas configurado para este agente.'
                    : 'No hay webhook de mensajes configurado para este agente.',
            ], 422);
        }

        $service = app(ContactQueueService::class);
        $plantillaForQueue = null;
        if ($type === ContactQueue::TYPE_WHATSAPP && $immediate && $idPlantilla) {
            $plantillas = $agent->messageConfig?->plantillas ?? [];
            $validIds = array_column($plantillas, 'id');
            if (in_array($idPlantilla, $validIds, true)) {
                $plantillaForQueue = $idPlantilla;
            }
        }
        if ($plantillaForQueue === null && $type === ContactQueue::TYPE_WHATSAPP && $immediate) {
            $plantillaForQueue = $agent->messageConfig?->default_plantilla_id;
        }

        $scheduledAtUtc = null;
        $scheduledAtLocal = null;
        if ($scheduledDate && ! $immediate) {
            $timeStr = ($scheduledTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $scheduledTime))
                ? substr($scheduledTime, 0, 5)
                : '08:00';
            $tzName = ($config?->schedule_config ?? [])['campaign_timezone'] ?? 'America/Bogota';
            $tz = new \DateTimeZone($tzName);
            $scheduledAtLocal = \Carbon\Carbon::parse($scheduledDate.' '.$timeStr.':00', $tz);
            $scheduledAtUtc = $scheduledAtLocal->copy()->utc();
        }

        $isRecurring = ! empty($scheduleConfigInput);
        $queue = $service->createQueue($agent, $type, $clientIds, $immediate, $plantillaForQueue, $scheduledAtUtc, $rules, $isRecurring);

        if ($immediate) {
            ProcessContactQueueJob::dispatch($queue);
        }

        $countMsg = $queue->usesRulesForSelection()
            ? 'los clientes que coincidan con las reglas en el momento de ejecución'
            : "{$queue->totalCount()} contacto(s)";
        $msg = $immediate
            ? "Cola creada. Se están procesando {$countMsg} en segundo plano."
            : ($scheduledAtLocal
                ? "Cola creada. Se ejecutará el {$scheduledAtLocal->format('Y-m-d')} a las {$scheduledAtLocal->format('H:i')} ({$countMsg})."
                : "Cola creada. Se ejecutará en los horarios configurados ({$countMsg}).");

        return response()->json([
            'success' => true,
            'message' => $msg,
            'queue_id' => $queue->id,
            'next_run_at' => $queue->next_run_at?->toIso8601String(),
        ]);
    }

    public function initiateWhatsapp(Request $request, Agent $agent, Client $client, \App\Services\TwilioContentService $twilio)
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $agent->load(['messageConfig', 'endpoints', 'clientSourceEndpoints']);

        if (! $client->phone) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene teléfono registrado.',
            ], 422);
        }

        $plantillas = $agent->messageConfig?->plantillas ?? [];

        $idPlantilla = $request->input('id_plantilla');
        if ($idPlantilla !== null && $idPlantilla !== '') {
            $validIds = array_column($plantillas, 'id');
            if (! in_array($idPlantilla, $validIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La plantilla seleccionada no es válida.',
                ], 422);
            }
        } else {
            $idPlantilla = $agent->messageConfig?->default_plantilla_id;
        }

        // Si la plantilla es de Twilio (Content SID), se envía directo por Twilio
        // sin pasar por el webhook externo (las plantillas ya viven en el sistema).
        $plantilla = collect($plantillas)->first(fn ($p) => ($p['id'] ?? null) === $idPlantilla);
        $esPlantillaTwilio = $idPlantilla
            && (($plantilla['from_twilio'] ?? false) || str_starts_with((string) $idPlantilla, 'HX'));

        if ($esPlantillaTwilio && $twilio->canSendWhatsapp()) {
            $variables = $agent->messageConfig?->resolvePlantillaVariables($idPlantilla, $client, $agent) ?? [];
            $variablesMap = [];
            foreach ($variables as $v) {
                $variablesMap[(string) ($v['name'] ?? '')] = (string) ($v['value'] ?? '');
            }

            try {
                $twilio->sendWhatsappTemplate($client->phone, (string) $idPlantilla, $variablesMap);

                ClientContactLog::create([
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'channel' => ClientContactLog::CHANNEL_WHATSAPP,
                    'contacted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Mensaje de WhatsApp enviado por Twilio.',
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar por Twilio: '.$e->getMessage(),
                ], 502);
            }
        }

        // Respaldo: webhook externo (plantillas manuales / integraciones existentes).
        $webhookUrl = $agent->messageConfig?->webhook_url;
        if (! $webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => $esPlantillaTwilio
                    ? 'Twilio no está configurado para enviar WhatsApp (define TWILIO_WHATSAPP_FROM).'
                    : 'No hay forma de enviar: elige una plantilla de Twilio o configura un webhook de mensajes.',
            ], 422);
        }

        $endpointsPayload = $agent->endpoints->map(fn ($ep) => [
            'name' => $ep->name,
            'url' => $ep->url,
            'method' => $ep->method,
            'headers' => $ep->headers ?? [],
            'client_parameter' => $ep->client_parameter,
        ])->values()->all();

        $clientSourcePayload = $agent->clientSourceEndpoints->map(fn ($ep) => [
            'name' => $ep->name,
            'url' => $ep->url,
            'headers' => $ep->headers ?? [],
        ])->values()->all();

        $payload = [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'lastname' => $client->lastname,
                'email' => $client->email,
                'phone' => $client->phone,
                'document_type' => $client->document_type,
                'document' => $client->document,
                'custom_fields' => $client->custom_fields ?? [],
                'form_url' => AgentFormResponse::urlForClient($agent, $client),
            ],
            'endpoints' => $endpointsPayload,
            'client_source_endpoints' => $clientSourcePayload,
        ];

        if ($idPlantilla !== null && $idPlantilla !== '') {
            $payload['id_plantilla'] = $idPlantilla;

            $variables = $agent->messageConfig?->resolvePlantillaVariables($idPlantilla, $client, $agent) ?? [];
            if ($variables !== []) {
                $payload['variables'] = $variables;
            }
        }

        try {
            $response = Http::timeout(30)->post($webhookUrl, $payload);

            if ($response->successful()) {
                ClientContactLog::create([
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'channel' => ClientContactLog::CHANNEL_WHATSAPP,
                    'contacted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Mensaje enviado al webhook correctamente.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'El webhook respondió con error: '.$response->status(),
            ], 502);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al contactar el webhook: '.$e->getMessage(),
            ], 502);
        }
    }

    /**
     * Envía un mensaje de texto libre de WhatsApp por Twilio (mensaje de sesión,
     * dentro de la ventana de 24h). Fuera de la ventana Twilio lo rechaza y hay
     * que usar una plantilla.
     */
    public function sendWhatsappMessage(Request $request, Agent $agent, Client $client, \App\Services\TwilioContentService $twilio)
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $validated = $request->validate(['body' => 'required|string|max:4000']);

        if (! $client->phone) {
            return response()->json(['success' => false, 'message' => 'El cliente no tiene teléfono registrado.'], 422);
        }
        if (! $twilio->canSendWhatsapp()) {
            return response()->json(['success' => false, 'message' => 'Twilio no está configurado para enviar WhatsApp (define TWILIO_WHATSAPP_FROM).'], 422);
        }

        try {
            $twilio->sendWhatsappText($client->phone, $validated['body']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar. Si pasaron más de 24h desde el último mensaje del cliente, debes reabrir con una plantilla. ('.$e->getMessage().')',
            ], 502);
        }

        // Se registra como saliente del agente nativo para que aparezca en el historial.
        \App\Models\TwilioMessage::create([
            'agent_id' => $agent->id,
            'channel' => 'whatsapp',
            'from_number' => preg_replace('/\D/', '', (string) $client->phone),
            'direction' => 'outbound',
            'body' => $validated['body'],
        ]);

        ClientContactLog::create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'channel' => ClientContactLog::CHANNEL_WHATSAPP,
            'contacted_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Mensaje enviado.']);
    }

    /**
     * Pausa o reactiva la respuesta automática de la IA para este cliente
     * (toma de control humano). El webhook de Twilio respeta este flag.
     */
    public function setAiPause(Request $request, Agent $agent, Client $client)
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $validated = $request->validate(['paused' => 'required|boolean']);
        $client->update(['ai_paused' => $validated['paused']]);

        return response()->json(['success' => true, 'ai_paused' => (bool) $client->ai_paused]);
    }

    public function initiateCall(Agent $agent, Client $client, \App\Services\ContactQueueService $queues)
    {
        $this->authorize('view', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $agent->load(['callConfig', 'clientSourceEndpoints']);

        $webhookUrl = $agent->callConfig?->webhook_url;
        if (! $webhookUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No hay webhook de llamadas configurado para este agente.',
            ], 422);
        }

        // Payload compartido con la cola de llamadas: agent_id + phone_number_id de
        // ElevenLabs, datos del cliente, variables dinámicas y datos de precarga.
        $payload = $queues->buildCallPayload($agent, $client);

        try {
            $response = Http::timeout(30)->post($webhookUrl, $payload);

            if ($response->successful()) {
                ClientContactLog::create([
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'channel' => ClientContactLog::CHANNEL_CALL,
                    'contacted_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Llamada iniciada correctamente.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'El webhook respondió con error: '.$response->status(),
            ], 502);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al contactar el webhook: '.$e->getMessage(),
            ], 502);
        }
    }

    public function destroy(Agent $agent, Client $client)
    {
        $this->authorize('update', $agent);

        if ($client->agent_id !== $agent->id) {
            abort(404);
        }

        $this->authorize('delete', $client);

        $client->delete();

        return back()->with('success', 'Cliente eliminado.');
    }

    public function downloadTemplate(Request $request, Agent $agent): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $agent);

        $agent->load('clientFields');
        $headers = ['nombre', 'apellido', 'email', 'telefono', 'tipo_documento', 'documento'];
        foreach ($agent->clientFields as $field) {
            $headers[] = $field->field_name;
        }
        $exampleRow = ['Ejemplo', 'Usuario', 'ejemplo@email.com', '+573001234567', 'CC', '12345678'];
        foreach ($agent->clientFields as $field) {
            $exampleRow[] = 'valor_ejemplo';
        }

        $format = $request->query('format', 'xlsx');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $exampleRow) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);
                fputcsv($handle, $exampleRow);
                fclose($handle);
            }, 'plantilla_clientes.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return Excel::download(
            new ClientTemplateExport($headers, $exampleRow),
            'plantilla_clientes.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function import(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $isExcel = in_array($extension, ['xlsx', 'xls']);

        $map = [
            'nombre' => 'name',
            'apellido' => 'lastname',
            'lastname' => 'lastname',
            'email' => 'email',
            'telefono' => 'phone',
            'phone' => 'phone',
            'tipo_documento' => 'document_type',
            'document_type' => 'document_type',
            'documento' => 'document',
            'document' => 'document',
        ];
        $customFieldNames = $agent->clientFields->pluck('field_name')->toArray();
        foreach ($customFieldNames as $fn) {
            $map[strtolower($fn)] = $fn;
        }

        $loadedAt = now();
        $imported = 0;
        $errors = [];

        if ($isExcel) {
            DB::beginTransaction();
            try {
                $import = new ClientsImport($agent, $customFieldNames, $map, $loadedAt);
                Excel::import($import, $file);
                $imported = $import->imported;
                $errors = $import->errors;
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al importar: '.$e->getMessage(),
                        'errors' => [],
                    ], 422);
                }

                return back()->withErrors(['file' => 'Error al importar: '.$e->getMessage()]);
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);
            if (! $header) {
                fclose($handle);

                return back()->withErrors(['file' => 'El archivo está vacío o no es válido.']);
            }

            $header = array_map('trim', array_map('strtolower', $header));
            $rowNum = 1;

            DB::beginTransaction();
            try {
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    if (count($row) < 2) {
                        continue;
                    }

                    $data = [];
                    foreach ($header as $i => $h) {
                        $key = $map[$h] ?? $h;
                        $data[$key] = $row[$i] ?? '';
                    }

                    $name = trim($data['name'] ?? $data['nombre'] ?? '');
                    $lastname = trim($data['lastname'] ?? $data['apellido'] ?? '');
                    $email = trim($data['email'] ?? '');

                    if (! $name || ! $lastname || ! $email) {
                        $errors[] = "Fila {$rowNum}: faltan nombre, apellido o email";

                        continue;
                    }

                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Fila {$rowNum}: email inválido ({$email})";

                        continue;
                    }

                    $customFields = [];
                    foreach ($customFieldNames as $fn) {
                        $customFields[$fn] = $data[$fn] ?? '';
                    }

                    $phoneRaw = $data['phone'] ?? $data['telefono'] ?? null;
                    $phoneNormalized = $phoneRaw !== null && $phoneRaw !== '' ? preg_replace('/\D/', '', $phoneRaw) : null;
                    $client = null;
                    if ($phoneNormalized !== null && $phoneNormalized !== '') {
                        $client = $agent->clients()->where('phone', $phoneNormalized)->first();
                    }
                    if (! $client) {
                        $client = $agent->clients()->where('email', $email)->first();
                    }

                    if ($client) {
                        $client->update([
                            'name' => $name,
                            'lastname' => $lastname,
                            'email' => $email,
                            'phone' => $phoneNormalized ?? $client->phone,
                            'document_type' => $data['document_type'] ?? $data['tipo_documento'] ?? $client->document_type,
                            'document' => $data['document'] ?? $data['documento'] ?? $client->document,
                            'custom_fields' => $customFields ?: $client->custom_fields,
                            'loaded_at' => $loadedAt,
                        ]);
                    } else {
                        $client = $agent->clients()->create([
                            'name' => $name,
                            'lastname' => $lastname,
                            'email' => $email,
                            'phone' => $phoneNormalized,
                            'document_type' => $data['document_type'] ?? $data['tipo_documento'] ?? null,
                            'document' => $data['document'] ?? $data['documento'] ?? null,
                            'custom_fields' => $customFields ?: null,
                            'loaded_at' => $loadedAt,
                        ]);
                    }
                    ClientLoadDate::create([
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'loaded_at' => $loadedAt,
                        'source' => ClientLoadDate::SOURCE_IMPORT,
                    ]);
                    $imported++;
                }
                fclose($handle);
                DB::commit();
            } catch (\Throwable $e) {
                if (isset($handle) && is_resource($handle)) {
                    fclose($handle);
                }
                DB::rollBack();
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al importar: '.$e->getMessage(),
                        'errors' => [],
                    ], 422);
                }

                return back()->withErrors(['file' => 'Error al importar: '.$e->getMessage()]);
            }
        }

        $msg = "Se importaron {$imported} clientes.";
        if (count($errors) > 0) {
            $msg .= ' ('.count($errors).' errores)';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'imported' => $imported,
                'errors' => $errors,
            ]);
        }

        return back()->with('success', $msg)->with('import_errors', $errors);
    }

    public function exportClients(Request $request, Agent $agent): StreamedResponse
    {
        $this->authorize('view', $agent);

        $query = $agent->clients()->latest();
        app(ClientListFilterService::class)->apply($request, $query);

        $agent->load('clientFields');
        $dynamicNames = $agent->clientFields->pluck('field_name')->all();

        $filename = 'clientes_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $agent, $dynamicNames) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            $headers = ['Nombre', 'Apellido', 'Email', 'Teléfono', 'Tipo documento', 'Documento'];
            foreach ($agent->clientFields as $f) {
                $headers[] = $f->field_name;
            }
            $headers[] = 'Otros datos recolectados';
            $headers[] = 'Fecha carga';
            fputcsv($handle, $headers);

            foreach ($query->cursor() as $client) {
                $cf = $client->custom_fields ?? [];
                $extra = array_diff_key($cf, array_flip($dynamicNames));
                $extraStr = collect($extra)->map(fn ($v, $k) => $k.': '.(is_array($v) ? json_encode($v) : $v))->implode('; ');

                $row = [
                    $client->name,
                    $client->lastname,
                    $client->email,
                    $client->phone ?? '',
                    $client->document_type ?? '',
                    $client->document ?? '',
                ];
                foreach ($agent->clientFields as $f) {
                    $row[] = $cf[$f->field_name] ?? '';
                }
                $row[] = $extraStr;
                $row[] = $client->loaded_at ? $client->loaded_at->format('Y-m-d H:i') : ($client->created_at?->format('Y-m-d H:i') ?? '');
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportCollectionData(Request $request, Agent $agent): StreamedResponse
    {
        $this->authorize('view', $agent);

        $query = $agent->clients()->withCollectionData();
        if ($request->filled('date_from')) {
            $query->where('updated_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('updated_at', '<=', $request->date_to.' 23:59:59');
        }

        $agent->load('clientFields');
        $dynamicNames = $agent->clientFields->pluck('field_name')->all();

        $filename = 'datos_recolectados_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $dynamicNames) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            $headers = ['Nombre', 'Apellido', 'Email', 'Teléfono', 'Última actualización'];
            $clients = $query->orderBy('updated_at', 'desc')->get();
            $extraKeys = [];
            foreach ($clients as $c) {
                $cf = $c->custom_fields ?? [];
                foreach (array_diff_key($cf, array_flip($dynamicNames)) as $k => $v) {
                    $extraKeys[$k] = true;
                }
            }
            $headers = array_merge($headers, array_keys($extraKeys));
            fputcsv($handle, $headers);

            foreach ($clients as $client) {
                $cf = $client->custom_fields ?? [];
                $extra = array_diff_key($cf, array_flip($dynamicNames));

                $row = [
                    $client->name,
                    $client->lastname,
                    $client->email ?? '',
                    $client->phone ?? '',
                    $client->updated_at?->format('Y-m-d H:i') ?? '',
                ];
                foreach (array_keys($extraKeys) as $key) {
                    $val = $extra[$key] ?? '';
                    $row[] = is_array($val) ? json_encode($val) : $val;
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Transcripciones desde Supabase PostgREST (solo URL + anon key; sin PDO).
     */
    /**
     * @param  string|null  $campanaQuery  ?campana= de la petición; null = usar CAMPANA_AINOA en el servicio.
     */
    private function callTranscriptViaSupabaseRest(Agent $agent, string $phoneDigits, int $callId, ?string $campanaQuery): JsonResponse
    {
        $svc = app(SupabaseCallTranscriptsRestService::class);

        try {
            if ($callId > 0) {
                $registro = $svc->findById($callId, $campanaQuery);
                if (! $registro) {
                    return $this->callTranscriptJson(['call' => null, 'message' => 'Llamada no encontrada']);
                }
                $storedDigits = preg_replace('/\D/', '', (string) ($registro['phone'] ?? ''));
                if ($storedDigits !== $phoneDigits && ! str_ends_with($storedDigits, $phoneDigits) && ! str_ends_with($phoneDigits, $storedDigits)) {
                    return $this->callTranscriptJson(['call' => null, 'message' => 'Llamada no pertenece a este cliente']);
                }

                $transcript = $this->normalizeTranscriptForCallModal(
                    isset($registro['transcript']) && is_string($registro['transcript']) ? $registro['transcript'] : null
                );
                $variablesExtraidas = $registro['variables_extraidas'] ?? null;
                if (is_string($variablesExtraidas)) {
                    $decoded = json_decode($variablesExtraidas, true);
                    $variablesExtraidas = is_array($decoded) ? $decoded : null;
                }
                $audio = null;
                $convId = isset($registro['conversation_id']) && is_string($registro['conversation_id'])
                    ? $registro['conversation_id']
                    : null;
                if ($convId) {
                    $audioRow = RegistroAudioLlamada::where('conversation_id', $convId)
                        ->whereNotNull('audio')->where('audio', '!=', '')->first(['audio']);
                    $audio = $audioRow?->audio;
                    if (($audio === null || $audio === '') && CallTranscriptsConnection::usesRest()) {
                        try {
                            $audio = app(SupabaseCallAudioRestService::class)->findAudioBase64($convId);
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }
                $createdAt = $registro['created_at'] ?? null;

                return $this->callTranscriptJson([
                    'call' => [
                        'id' => $registro['id'],
                        'created_at' => $createdAt ? Carbon::parse($createdAt)->toIso8601String() : null,
                        'duration_call_seg' => $registro['duration_call_seg'] ?? null,
                        'conversation_id' => $convId,
                        'transcript' => $transcript,
                        'variables_extraidas' => $variablesExtraidas,
                        'audio' => $audio,
                    ],
                ]);
            }

            $rows = $svc->listRowsForPhoneAndCampana($phoneDigits, $campanaQuery);
            $registros = collect($rows)->filter(function ($r) use ($phoneDigits) {
                $storedDigits = preg_replace('/\D/', '', (string) ($r['phone'] ?? ''));

                return $storedDigits === $phoneDigits
                    || str_ends_with($storedDigits, $phoneDigits)
                    || str_ends_with($phoneDigits, $storedDigits);
            })->values();

            if ($registros->isEmpty()) {
                return $this->callTranscriptJson(['calls' => [], 'message' => 'No hay llamadas registradas para este cliente']);
            }

            $convIds = $registros->pluck('conversation_id')->filter()->unique()->values()->all();
            try {
                $conversationIdsWithAudio = app(SupabaseCallAudioRestService::class)->filterConversationIdsWithAudio($convIds);
            } catch (\Throwable $e) {
                report($e);
                $conversationIdsWithAudio = [];
            }

            $calls = [];
            foreach ($registros as $registro) {
                $convId = isset($registro['conversation_id']) && is_string($registro['conversation_id'])
                    ? $registro['conversation_id']
                    : null;
                $createdAt = $registro['created_at'] ?? null;
                $calls[] = [
                    'id' => $registro['id'],
                    'created_at' => $createdAt ? Carbon::parse($createdAt)->toIso8601String() : null,
                    'duration_call_seg' => $registro['duration_call_seg'] ?? null,
                    'conversation_id' => $convId,
                    'has_audio' => $convId !== null && $convId !== '' && in_array($convId, $conversationIdsWithAudio, true),
                ];
            }

            return $this->callTranscriptJson(['calls' => $calls]);
        } catch (\Throwable $e) {
            report($e);
            $msg = 'No se pudieron cargar las transcripciones desde Supabase. Revisa VITE_SUPABASE_URL y VITE_SUPABASE_ANON_KEY, y políticas RLS en Supabase.';
            if ($callId > 0) {
                return $this->callTranscriptJson(['call' => null, 'message' => $msg]);
            }

            return $this->callTranscriptJson(['calls' => [], 'message' => $msg]);
        }
    }

    /**
     * Incluye transcripts_source para diagnosticar en producción (internal vs supabase).
     *
     * @param  array<string, mixed>  $data
     */
    private function callTranscriptJson(array $data): JsonResponse
    {
        $data['transcripts_source'] = CallTranscriptsConnection::source();

        return response()->json($data);
    }

    /**
     * El conversation_id debe pertenecer a una fila de registro escrito con el teléfono/campaña del cliente.
     */
    private function clientOwnsConversationForTranscript(Agent $agent, string $phoneDigits, ?string $campanaQuery, string $conversationId): bool
    {
        if (CallTranscriptsConnection::usesRest()) {
            return app(SupabaseCallTranscriptsRestService::class)
                ->rowExistsForConversation($phoneDigits, $campanaQuery, $conversationId);
        }

        $campanaEloquent = $campanaQuery ?? config('services.campana_ainoa');
        $campanaEloquent = is_string($campanaEloquent) ? trim($campanaEloquent) : '';

        $registro = RegistroEscritoLlamada::query()
            ->forCampanaValue($campanaEloquent !== '' ? $campanaEloquent : null)
            ->where('agent_id', (string) $agent->id)
            ->where('conversation_id', $conversationId)
            ->first();

        if (! $registro) {
            return false;
        }

        $storedDigits = preg_replace('/\D/', '', (string) $registro->phone);

        return $storedDigits === $phoneDigits
            || str_ends_with($storedDigits, $phoneDigits)
            || str_ends_with($phoneDigits, $storedDigits);
    }

    /**
     * Supabase puede guardar transcript como JSON de turnos o como texto. El modal espera [{ role, message }].
     *
     * @return list<array{role: string, message: string}>
     */
    private function normalizeTranscriptForCallModal(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (isset($row['message']) && (string) $row['message'] !== '') {
                    $out[] = [
                        'role' => ($row['role'] ?? 'user') === 'agent' ? 'agent' : 'user',
                        'message' => (string) $row['message'],
                    ];

                    continue;
                }
                if (isset($row['content']) && (string) $row['content'] !== '') {
                    $role = 'user';
                    if (($row['role'] ?? '') === 'agent' || ($row['type'] ?? '') === 'agent') {
                        $role = 'agent';
                    }
                    $out[] = [
                        'role' => $role,
                        'message' => (string) $row['content'],
                    ];
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        return [['role' => 'agent', 'message' => $raw]];
    }
}
