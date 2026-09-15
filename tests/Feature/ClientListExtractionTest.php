<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentExtractionVariable;
use App\Models\Client;
use App\Models\ConversationExtraction;
use App\Models\User;
use App\Services\ConversationExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientListExtractionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'two_factor_secret' => 'x',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function makeAgent(User $owner): Agent
    {
        return Agent::create([
            'name' => 'Agente',
            'status' => 'active',
            'prompt_configuration' => ['system_prompt' => 'x'],
            'user_id' => $owner->id,
        ]);
    }

    private function makeClient(Agent $agent, string $name, ?string $phone): Client
    {
        return Client::create([
            'agent_id' => $agent->id,
            'name' => $name,
            'lastname' => 'Apellido',
            'email' => strtolower($name).'@example.com',
            'phone' => $phone,
        ]);
    }

    public function test_el_servicio_cruza_variables_extraidas_por_telefono(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        // El from_number de WhatsApp trae código de país; el cliente NO.
        ConversationExtraction::create([
            'agent_id' => $agent->id,
            'from_number' => '+573001234567',
            'values' => ['tipo_documento' => 'CC', 'interes' => 'alto'],
        ]);

        $conCoincidencia = $this->makeClient($agent, 'Ana', '3001234567');
        $sinCoincidencia = $this->makeClient($agent, 'Beto', '3009999999');
        $sinTelefono = $this->makeClient($agent, 'Cielo', null);

        $clients = collect([$conCoincidencia, $sinCoincidencia, $sinTelefono]);
        app(ConversationExtractionService::class)->attachToClients($agent, $clients);

        $this->assertSame('CC', $conCoincidencia->extracted_variables['tipo_documento']);
        $this->assertSame('alto', $conCoincidencia->extracted_variables['interes']);
        $this->assertEquals((object) [], $sinCoincidencia->extracted_variables);
        $this->assertEquals((object) [], $sinTelefono->extracted_variables);
    }

    public function test_el_listado_incluye_definiciones_y_valores_extraidos(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        AgentExtractionVariable::create([
            'agent_id' => $agent->id,
            'name' => 'tipo_documento',
            'label' => 'Tipo de documento',
            'description' => 'El tipo de documento del cliente',
            'type' => 'string',
            'order' => 1,
        ]);

        $this->makeClient($agent, 'Ana', '3001234567');
        ConversationExtraction::create([
            'agent_id' => $agent->id,
            'from_number' => '+573001234567',
            'values' => ['tipo_documento' => 'CC'],
        ]);

        $response = $this->actingAs($owner)->get(route('agents.clients.index', $agent));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Clients/Index')
            // La definición de la variable llega al frontend para pintar la columna.
            ->where('agent.extraction_variables.0.name', 'tipo_documento')
            ->where('agent.extraction_variables.0.label', 'Tipo de documento')
            // El valor extraído queda adjunto al cliente correcto.
            ->where('clients.data.0.extracted_variables.tipo_documento', 'CC')
        );
    }

    public function test_la_vista_show_del_agente_tambien_incluye_valores_extraidos(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        AgentExtractionVariable::create([
            'agent_id' => $agent->id,
            'name' => 'tipo_documento',
            'label' => 'Tipo de documento',
            'description' => 'El tipo de documento del cliente',
            'type' => 'string',
            'order' => 1,
        ]);

        $this->makeClient($agent, 'Ana', '3001234567');
        ConversationExtraction::create([
            'agent_id' => $agent->id,
            'from_number' => '+573001234567',
            'values' => ['tipo_documento' => 'CC'],
        ]);

        $response = $this->actingAs($owner)->get(route('agents.show', $agent));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agents/Show')
            ->where('agent.extraction_variables.0.name', 'tipo_documento')
            ->where('clients.data.0.extracted_variables.tipo_documento', 'CC')
        );
    }
}
