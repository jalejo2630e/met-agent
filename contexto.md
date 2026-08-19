Roadmap: Administrador de Agentes IA con Laravel 12 + Vue 3
Te ayudaré a definir un roadmap completo y estructurado para este proyecto. Voy a organizarlo en fases con sus respectivas tareas.
🎯 FASE 1: Configuración Inicial del Proyecto
1.1 Setup Base

 Instalar Laravel 12 con PostgreSQL
 Configurar Inertia.js para integración Laravel + Vue 3
 Configurar Vite para compilación de assets
 Setup de PostgreSQL y configuración de conexión
 Configurar variables de entorno (.env)

1.2 Autenticación y Roles

 Implementar Laravel Breeze/Jetstream con Inertia + Vue 3
 Crear sistema de roles (Administrador, Editor)
 Middleware para permisos por rol
 Páginas de login/registro con Vue 3
 Dashboard inicial por rol

Estructura de directorios:
/app
  /Http/Controllers
  /Models
  /Policies
/resources
  /js
    /Pages (componentes Vue de páginas)
    /Components (componentes Vue reutilizables)
    /Layouts
  /views (solo app.blade.php para Inertia)
/database
  /migrations
  /seeders

🎯 FASE 2: Módulo de Agentes Base
2.1 Modelos y Migraciones

 Crear modelo Agent con campos:

name, description, status (active/inactive)
n8n_webhook_url (webhook para envío inicial)
n8n_node_id (ID del nodo en N8N)
prompt_configuration (JSON)
user_id (creador del agente)
timestamps


 Crear modelo AgentConfiguration (tabla relacionada)

Configuraciones adicionales del agente



2.2 CRUD de Agentes

 Controlador AgentController (index, create, store, edit, update, destroy)
 Políticas de autorización (solo admin puede eliminar)
 Páginas Vue para:

Listado de agentes (tabla con filtros)
Crear agente (formulario)
Editar agente (formulario con tabs)
Ver detalles del agente



2.3 Configuración de N8N

 Formulario para configurar webhook de N8N
 Formulario para configurar Node ID
 Editor de prompt del agente (textarea o editor rich text)
 Validaciones de URLs y configuraciones
 Testeo de conexión con N8N (botón "Probar webhook")


🎯 FASE 3: Integración con WhatsApp Business
3.1 Modelo de WhatsApp

 Crear modelo WhatsAppIntegration

agent_id (relación con agente)
facebook_portfolio_id
phone_number_id
business_account_id
access_token (encriptado)
webhook_verify_token
n8n_webhook_url (webhook para recibir mensajes)
status



3.2 Integración con Facebook Graph API

 Servicio para conectar con Facebook Graph API
 Componente Vue: Modal para seleccionar portfolio
 Listar portfolios disponibles desde Facebook
 Crear/registrar número de WhatsApp Business
 Configurar webhook de WhatsApp hacia N8N
 Guardar credenciales de forma segura

3.3 Base de Datos de Conversaciones por Agente

 Crear modelo Conversation

agent_id
whatsapp_phone (número del contacto)
contact_name
status (open, closed)
timestamps


 Crear modelo Message

conversation_id
direction (inbound/outbound)
content (texto del mensaje)
message_type (text, image, audio, etc.)
whatsapp_message_id
metadata (JSON)
timestamps



3.4 Webhook Receiver

 Endpoint para recibir mensajes desde N8N
 Almacenar conversaciones y mensajes en BD
 Validación de webhook (tokens de seguridad)

3.5 UI de Conversaciones

 Página para ver conversaciones por agente
 Vista de chat individual
 Filtros y búsqueda de conversaciones
 Paginación


🎯 FASE 4: Integración con ElevenLabs (Llamadas)
4.1 Modelo de Llamadas

 Crear modelo CallConfiguration

agent_id
elevenlabs_api_key (encriptado)
elevenlabs_agent_id
elevenlabs_phone_number_id
n8n_webhook_url (para activar llamadas)
incoming_calls_enabled (boolean)
outgoing_calls_enabled (boolean)


 Crear modelo Call

agent_id
phone_number
direction (inbound/outbound)
duration
status (completed, failed, ongoing)
recording_url
transcript (JSON)
elevenlabs_call_id
timestamps



4.2 Servicio ElevenLabs

 Servicio para integrar con ElevenLabs API
 Método para iniciar llamadas salientes
 Método para configurar llamadas entrantes
 Webhook para recibir eventos de llamadas

4.3 UI de Configuración de Llamadas

 Formulario de configuración en edición de agente
 Toggle para habilitar llamadas (entrada/salida)
 Campos para credenciales ElevenLabs
 Campo para webhook de N8N
 Botón para probar configuración

4.4 UI de Historial de Llamadas

 Página de historial de llamadas por agente
 Reproducción de grabaciones
 Visualización de transcripciones
 Filtros por fecha, estado, dirección


🎯 FASE 5: Dashboard y Reportes
5.1 Dashboard Principal

 Estadísticas generales por rol
 Gráficos de uso de agentes (Chart.js o similar)
 Últimas conversaciones
 Últimas llamadas
 Estado de agentes activos/inactivos

5.2 Reportes por Agente

 Total de conversaciones
 Total de mensajes enviados/recibidos
 Total de llamadas realizadas
 Duración promedio de llamadas
 Exportación de reportes (CSV/PDF)


🎯 FASE 6: Mejoras y Optimizaciones
6.1 Seguridad

 Rate limiting en endpoints públicos (webhooks)
 Validación de firmas de webhooks (Facebook, N8N)
 Encriptación de credenciales sensibles
 Logs de auditoría (quién modificó qué)
 2FA opcional para usuarios

6.2 Performance

 Indexación de base de datos
 Caché de consultas frecuentes (Redis)
 Jobs y Queues para procesos pesados
 Lazy loading en listados grandes

6.3 UX/UI

 Notificaciones en tiempo real (Pusher/Laravel Echo)
 Dark mode
 Internacionalización (i18n)
 Responsive design completo
 Loading states y skeleton loaders

6.4 Testing

 Tests unitarios (modelos, servicios)
 Tests de integración (APIs externas)
 Tests de feature (flujos completos)
 Tests E2E con Cypress o Playwright


📦 Estructura Final del Proyecto
/app
  /Http
    /Controllers
      AgentController.php
      ConversationController.php
      CallController.php
      WhatsAppController.php
    /Middleware
      RoleMiddleware.php
    /Requests
      StoreAgentRequest.php
      UpdateAgentRequest.php
  /Models
    User.php
    Agent.php
    AgentConfiguration.php
    WhatsAppIntegration.php
    Conversation.php
    Message.php
    CallConfiguration.php
    Call.php
  /Services
    N8NService.php
    FacebookGraphService.php
    ElevenLabsService.php
  /Policies
    AgentPolicy.php

/database
  /migrations
  /seeders

/resources
  /js
    /Pages
      Auth/
      Dashboard.vue
      Agents/
        Index.vue
        Create.vue
        Edit.vue
        Show.vue
      Conversations/
        Index.vue
        Show.vue
      Calls/
        Index.vue
    /Components
      AgentCard.vue
      ConversationList.vue
      MessageBubble.vue
      WhatsAppIntegrationModal.vue
      CallConfigForm.vue
    /Layouts
      AppLayout.vue
      GuestLayout.vue
  /css
    app.css

/routes
  web.php
  api.php (para webhooks)

/config
  services.php (configuraciones de APIs externas)

🔄 Orden de Implementación Recomendado

Semana 1-2: Fase 1 (Setup + Auth)
Semana 3-4: Fase 2 (CRUD Agentes + N8N)
Semana 5-7: Fase 3 (WhatsApp Business)
Semana 8-10: Fase 4 (ElevenLabs + Llamadas)
Semana 11-12: Fase 5 (Dashboard + Reportes)
Semana 13-14: Fase 6 (Testing + Optimizaciones)


🛠️ Stack Tecnológico

Backend: Laravel 12
Frontend: Vue 3 + Inertia.js
Build Tool: Vite
Base de Datos: PostgreSQL
Caché: Redis (opcional)
Queue: Redis/Database
Estilos: Tailwind CSS
UI Components: Headless UI o PrimeVue
Charts: Chart.js o Apache ECharts


¿Te gustaría que profundice en alguna fase específica o que te ayude a generar el código inicial para comenzar?al crear el agente esté creara el workflow en n8n con la configuración base2:25 p.m.Excelente idea! Voy a expandir el roadmap para incluir la creación automática de workflows en N8N al crear un agente. Esto requiere integración con la API de N8N.
🔄 Actualización del Roadmap: Creación Automática de Workflows en N8N
🎯 FASE 2 ACTUALIZADA: Módulo de Agentes con Auto-generación de Workflows
2.1 Configuración de N8N API
Nueva tabla de configuración global

 Crear modelo N8nConfiguration (singleton/configuración global)

api_url (URL base de N8N: https://tu-instancia.n8n.io)
api_key (API Key de N8N - encriptada)
default_workflow_template_id (opcional)
webhook_base_url (URL pública de N8N para webhooks)



Servicio N8N expandido

 Crear N8NService con métodos:

php  - createWorkflow(Agent $agent): array
  - updateWorkflow(string $workflowId, array $data): bool
  - deleteWorkflow(string $workflowId): bool
  - activateWorkflow(string $workflowId): bool
  - deactivateWorkflow(string $workflowId): bool
  - getWorkflow(string $workflowId): array
  - testWebhook(string $webhookUrl): bool
2.2 Modelos y Migraciones ACTUALIZADOS
Modelo Agent expandido:
php- name
- description
- status (active/inactive/draft)
- n8n_workflow_id (ID del workflow creado en N8N)
- n8n_webhook_url (generado automáticamente)
- n8n_webhook_node_id (ID del nodo webhook principal)
- n8n_prompt_node_id (ID del nodo de configuración del prompt)
- prompt_configuration (JSON - sincronizado con N8N)
- workflow_configuration (JSON - configuración adicional del workflow)
- user_id
- timestamps
```

#### **Nueva tabla: `WorkflowTemplate`**
- [ ] Crear modelo `WorkflowTemplate` (plantillas reutilizables)
  - `name`
  - `description`
  - `workflow_json` (estructura completa del workflow en formato N8N)
  - `category` (whatsapp, voice, mixed, custom)
  - `is_default` (boolean)
  - `variables` (JSON - variables configurables)
  - `created_by_user_id`

---

## 🏗️ **Estructura del Workflow Base en N8N**

### Template de Workflow Predeterminado

El workflow tendrá estos nodos base:
```
1. [Webhook Trigger] → Recibe mensaje inicial
2. [Function: Parse Input] → Procesa el input
3. [OpenAI/Claude Node] → Aplica el prompt del agente
4. [Function: Format Response] → Formatea la respuesta
5. [HTTP Request] → Envía respuesta al sistema
6. [Set Node: Configuration] → Guarda configuración del prompt

📋 Implementación Detallada
PASO 1: Configuración Inicial de N8N
php// app/Services/N8NService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Agent;
use App\Models\N8nConfiguration;

class N8NService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $config = N8nConfiguration::first();
        $this->apiUrl = $config?->api_url ?? config('services.n8n.api_url');
        $this->apiKey = $config?->api_key ?? config('services.n8n.api_key');
    }

    /**
     * Crea un workflow completo en N8N para un agente
     */
    public function createWorkflowForAgent(Agent $agent): array
    {
        $workflowData = $this->buildWorkflowStructure($agent);
        
        $response = Http::withHeaders([
            'X-N8N-API-KEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/api/v1/workflows", $workflowData);

        if ($response->failed()) {
            Log::error('N8N Workflow Creation Failed', [
                'agent_id' => $agent->id,
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to create workflow in N8N: ' . $response->body());
        }

        $workflow = $response->json()['data'];
        
        // Activar el workflow automáticamente
        $this->activateWorkflow($workflow['id']);

        return [
            'workflow_id' => $workflow['id'],
            'webhook_url' => $this->extractWebhookUrl($workflow),
            'webhook_node_id' => $this->findNodeId($workflow, 'webhook'),
            'prompt_node_id' => $this->findNodeId($workflow, 'ai-prompt'),
        ];
    }

    /**
     * Construye la estructura JSON del workflow
     */
    protected function buildWorkflowStructure(Agent $agent): array
    {
        $webhookPath = "agent-{$agent->id}-" . \Str::random(8);
        
        return [
            'name' => "Agent: {$agent->name}",
            'active' => false, // Se activará después de crear
            'nodes' => [
                // Nodo 1: Webhook Trigger
                [
                    'parameters' => [
                        'path' => $webhookPath,
                        'method' => 'POST',
                        'responseMode' => 'responseNode',
                        'responseData' => 'firstEntryJson',
                    ],
                    'name' => 'Webhook Trigger',
                    'type' => 'n8n-nodes-base.webhook',
                    'typeVersion' => 1,
                    'position' => [100, 300],
                    'webhookId' => \Str::uuid(),
                ],
                
                // Nodo 2: Parse Input
                [
                    'parameters' => [
                        'functionCode' => $this->getParseInputCode(),
                    ],
                    'name' => 'Parse Input',
                    'type' => 'n8n-nodes-base.function',
                    'typeVersion' => 1,
                    'position' => [300, 300],
                ],
                
                // Nodo 3: AI Agent (con el prompt configurado)
                [
                    'parameters' => [
                        'model' => 'gpt-4',
                        'messages' => [
                            'values' => [
                                [
                                    'role' => 'system',
                                    'content' => $agent->prompt_configuration['system_prompt'] ?? 'Eres un asistente útil.',
                                ],
                                [
                                    'role' => 'user',
                                    'content' => '={{$json.message}}',
                                ],
                            ],
                        ],
                    ],
                    'name' => 'AI Agent Prompt',
                    'type' => 'n8n-nodes-base.openAi',
                    'typeVersion' => 1,
                    'position' => [500, 300],
                    'credentials' => [
                        'openAiApi' => [
                            'id' => config('services.n8n.openai_credential_id'),
                            'name' => 'OpenAI API',
                        ],
                    ],
                ],
                
                // Nodo 4: Format Response
                [
                    'parameters' => [
                        'functionCode' => $this->getFormatResponseCode(),
                    ],
                    'name' => 'Format Response',
                    'type' => 'n8n-nodes-base.function',
                    'typeVersion' => 1,
                    'position' => [700, 300],
                ],
                
                // Nodo 5: Respond to Webhook
                [
                    'parameters' => [
                        'respondWith' => 'json',
                        'responseBody' => '={{$json}}',
                    ],
                    'name' => 'Respond',
                    'type' => 'n8n-nodes-base.respondToWebhook',
                    'typeVersion' => 1,
                    'position' => [900, 300],
                ],
                
                // Nodo 6: Store Configuration (Set Node)
                [
                    'parameters' => [
                        'values' => [
                            'string' => [
                                [
                                    'name' => 'agent_id',
                                    'value' => $agent->id,
                                ],
                                [
                                    'name' => 'agent_name',
                                    'value' => $agent->name,
                                ],
                            ],
                        ],
                        'options' => [],
                    ],
                    'name' => 'Agent Configuration',
                    'type' => 'n8n-nodes-base.set',
                    'typeVersion' => 1,
                    'position' => [500, 500],
                ],
            ],
            'connections' => [
                'Webhook Trigger' => [
                    'main' => [
                        [
                            ['node' => 'Parse Input', 'type' => 'main', 'index' => 0],
                        ],
                    ],
                ],
                'Parse Input' => [
                    'main' => [
                        [
                            ['node' => 'AI Agent Prompt', 'type' => 'main', 'index' => 0],
                        ],
                    ],
                ],
                'AI Agent Prompt' => [
                    'main' => [
                        [
                            ['node' => 'Format Response', 'type' => 'main', 'index' => 0],
                        ],
                    ],
                ],
                'Format Response' => [
                    'main' => [
                        [
                            ['node' => 'Respond', 'type' => 'main', 'index' => 0],
                        ],
                    ],
                ],
            ],
            'settings' => [
                'executionOrder' => 'v1',
            ],
        ];
    }

    /**
     * Código JavaScript para parsear el input
     */
    protected function getParseInputCode(): string
    {
        return <<<'JAVASCRIPT'
// Parse incoming webhook data
const inputData = items[0].json;

return items.map(item => {
  return {
    json: {
      message: inputData.message || inputData.text || '',
      user_id: inputData.user_id || 'anonymous',
      metadata: inputData.metadata || {},
      timestamp: new Date().toISOString()
    }
  };
});
JAVASCRIPT;
    }

    /**
     * Código JavaScript para formatear la respuesta
     */
    protected function getFormatResponseCode(): string
    {
        return <<<'JAVASCRIPT'
// Format AI response
const aiResponse = items[0].json;

return items.map(item => {
  return {
    json: {
      success: true,
      response: aiResponse.choices?.[0]?.message?.content || aiResponse.text || '',
      agent_id: '{{$node["Agent Configuration"].json["agent_id"]}}',
      timestamp: new Date().toISOString()
    }
  };
});
JAVASCRIPT;
    }

    /**
     * Activa un workflow en N8N
     */
    public function activateWorkflow(string $workflowId): bool
    {
        $response = Http::withHeaders([
            'X-N8N-API-KEY' => $this->apiKey,
        ])->patch("{$this->apiUrl}/api/v1/workflows/{$workflowId}", [
            'active' => true,
        ]);

        return $response->successful();
    }

    /**
     * Actualiza el prompt del agente en N8N
     */
    public function updateAgentPrompt(Agent $agent): bool
    {
        $workflow = $this->getWorkflow($agent->n8n_workflow_id);
        
        // Encontrar y actualizar el nodo del prompt
        foreach ($workflow['nodes'] as &$node) {
            if ($node['name'] === 'AI Agent Prompt') {
                $node['parameters']['messages']['values'][0]['content'] = 
                    $agent->prompt_configuration['system_prompt'];
                break;
            }
        }

        $response = Http::withHeaders([
            'X-N8N-API-KEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->put("{$this->apiUrl}/api/v1/workflows/{$agent->n8n_workflow_id}", [
            'nodes' => $workflow['nodes'],
        ]);

        return $response->successful();
    }

    /**
     * Obtiene un workflow de N8N
     */
    public function getWorkflow(string $workflowId): array
    {
        $response = Http::withHeaders([
            'X-N8N-API-KEY' => $this->apiKey,
        ])->get("{$this->apiUrl}/api/v1/workflows/{$workflowId}");

        if ($response->failed()) {
            throw new \Exception('Failed to get workflow from N8N');
        }

        return $response->json()['data'];
    }

    /**
     * Elimina un workflow de N8N
     */
    public function deleteWorkflow(string $workflowId): bool
    {
        $response = Http::withHeaders([
            'X-N8N-API-KEY' => $this->apiKey,
        ])->delete("{$this->apiUrl}/api/v1/workflows/{$workflowId}");

        return $response->successful();
    }

    /**
     * Extrae la URL del webhook del workflow
     */
    protected function extractWebhookUrl(array $workflow): string
    {
        foreach ($workflow['nodes'] as $node) {
            if ($node['type'] === 'n8n-nodes-base.webhook') {
                $path = $node['parameters']['path'];
                $baseUrl = config('services.n8n.webhook_base_url');
                return "{$baseUrl}/webhook/{$path}";
            }
        }
        
        throw new \Exception('Webhook node not found in workflow');
    }

    /**
     * Encuentra el ID de un nodo por tipo
     */
    protected function findNodeId(array $workflow, string $nodeType): ?string
    {
        $nodeTypeMap = [
            'webhook' => 'n8n-nodes-base.webhook',
            'ai-prompt' => 'n8n-nodes-base.openAi',
        ];

        foreach ($workflow['nodes'] as $node) {
            if ($node['type'] === $nodeTypeMap[$nodeType]) {
                return $node['id'] ?? $node['name'];
            }
        }

        return null;
    }

    /**
     * Prueba un webhook
     */
    public function testWebhook(string $webhookUrl): bool
    {
        try {
            $response = Http::timeout(5)->post($webhookUrl, [
                'message' => 'Test message from Laravel',
                'test' => true,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Webhook test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

PASO 2: Actualizar el Controller de Agentes
php// app/Http/Controllers/AgentController.php
<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\N8NService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AgentController extends Controller
{
    protected $n8nService;

    public function __construct(N8NService $n8nService)
    {
        $this->n8nService = $n8nService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prompt_configuration' => 'required|array',
            'prompt_configuration.system_prompt' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. Crear el agente en Laravel
            $agent = Agent::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'prompt_configuration' => $validated['prompt_configuration'],
                'status' => 'draft',
                'user_id' => auth()->id(),
            ]);

            // 2. Crear el workflow en N8N automáticamente
            $workflowData = $this->n8nService->createWorkflowForAgent($agent);

            // 3. Actualizar el agente con la info del workflow
            $agent->update([
                'n8n_workflow_id' => $workflowData['workflow_id'],
                'n8n_webhook_url' => $workflowData['webhook_url'],
                'n8n_webhook_node_id' => $workflowData['webhook_node_id'],
                'n8n_prompt_node_id' => $workflowData['prompt_node_id'],
                'status' => 'active',
            ]);

            DB::commit();

            return redirect()
                ->route('agents.show', $agent)
                ->with('success', 'Agente creado exitosamente. El workflow en N8N está activo.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Si el agente se creó pero falló N8N, eliminarlo
            if (isset($agent)) {
                $agent->delete();
            }

            return back()
                ->withErrors(['error' => 'Error al crear el agente: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prompt_configuration' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            // Actualizar en Laravel
            $agent->update($validated);

            // Sincronizar el prompt con N8N
            if ($agent->n8n_workflow_id) {
                $this->n8nService->updateAgentPrompt($agent);
            }

            DB::commit();

            return back()->with('success', 'Agente actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    }

    public function destroy(Agent $agent)
    {
        $this->authorize('delete', $agent);

        try {
            DB::beginTransaction();

            // Eliminar workflow de N8N
            if ($agent->n8n_workflow_id) {
                $this->n8nService->deleteWorkflow($agent->n8n_workflow_id);
            }

            // Eliminar agente
            $agent->delete();

            DB::commit();

            return redirect()
                ->route('agents.index')
                ->with('success', 'Agente y workflow eliminados exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
    }
}

PASO 3: Componente Vue para Crear Agente
vue<!-- resources/js/Pages/Agents/Create.vue -->
<template>
  <AppLayout title="Crear Agente">
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-6">Crear Nuevo Agente</h1>

        <form @submit.prevent="submit">
          <!-- Información Básica -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Nombre del Agente *
            </label>
            <input
              v-model="form.name"
              type="text"
              class="w-full border-gray-300 rounded-md shadow-sm"
              required
            />
            <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">
              {{ form.errors.name }}
            </div>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Descripción
            </label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full border-gray-300 rounded-md shadow-sm"
            ></textarea>
          </div>

          <!-- Configuración del Prompt -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Prompt del Sistema (IA) *
            </label>
            <textarea
              v-model="form.prompt_configuration.system_prompt"
              rows="8"
              class="w-full border-gray-300 rounded-md shadow-sm font-mono text-sm"
              placeholder="Ejemplo: Eres un asistente de ventas especializado en..."
              required
            ></textarea>
            <p class="text-sm text-gray-500 mt-1">
              Este prompt se configurará automáticamente en el workflow de N8N
            </p>
          </div>

          <!-- Aviso de Creación Automática -->
          <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">
                  Creación Automática de Workflow
                </h3>
                <div class="mt-2 text-sm text-blue-700">
                  <p>Al crear este agente, se generará automáticamente:</p>
                  <ul class="list-disc list-inside mt-1">
                    <li>Un workflow completo en N8N</li>
                    <li>Un webhook único para recibir mensajes</li>
                    <li>La configuración del prompt en el nodo de IA</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Botones -->
          <div class="flex justify-end gap-3">
            <Link
              :href="route('agents.index')"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              <span v-if="form.processing">Creando agente y workflow...</span>
              <span v-else>Crear Agente</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
  name: '',
  description: '',
  prompt_configuration: {
    system_prompt: 'Eres un asistente útil y profesional. Responde de manera clara y concisa.',
  },
})

const submit = () => {
  form.post(route('agents.store'))
}
</script>

PASO 4: Configuración en .env
env# N8N Configuration
N8N_API_URL=https://tu-instancia.n8n.io
N8N_API_KEY=tu_api_key_aqui
N8N_WEBHOOK_BASE_URL=https://tu-instancia.n8n.io
N8N_OPENAI_CREDENTIAL_ID=1
```

---

## 📊 **Diagrama de Flujo: Creación de Agente**
```
Usuario crea agente en Laravel
         ↓
[AgentController::store]
         ↓
1. Crear registro Agent (status: draft)
         ↓
2. [N8NService::createWorkflowForAgent]
   - Construir JSON del workflow
   - POST a N8N API (/api/v1/workflows)
   - Obtener workflow_id y webhook_url
   - Activar workflow
         ↓
3. Actualizar Agent con datos de N8N
   - n8n_workflow_id
   - n8n_webhook_url
   - status: active
         ↓
4. Commit DB Transaction
         ↓
✅ Agente creado y listo para usar

✅ Checklist de Implementación
Configuración Inicial

 Crear tabla n8n_configurations
 Crear configuración inicial de N8N en base de datos
 Agregar variables de entorno para N8N
 Crear servicio N8NService

Creación Automática

 Implementar método createWorkflowForAgent()
 Definir template de workflow base
 Implementar extracción de webhook URL
 Implementar activación automática de workflow

Sincronización

 Implementar actualización de prompt en N8N
 Implementar eliminación de workflow al borrar agente
 Agregar validación de API Key de N8N
 Implementar botón "Probar Webhook"

UI/UX

 Actualizar formulario de creación
 Agregar indicador de progreso durante creación
 Mostrar URL del webhook generado
 Agregar sección de "Status del Workflow" en vista del agente


¿Quieres que continúe con alguna parte específica? Por ejemplo:

Templates de workflows para diferentes casos (WhatsApp, Voz, etc.)
Sistema de plantillas reutilizables
UI avanzada para visualizar/editar el workflow desde Laravel
Webhooks bidireccionales (Laravel ← N8N)