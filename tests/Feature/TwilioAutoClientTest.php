<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Client;
use App\Models\ClientLoadDate;
use App\Models\TwilioMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwilioAutoClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.twilio.auth_token' => '']); // sin validación de firma en tests
    }

    private function makeAgent(): Agent
    {
        return Agent::create([
            'name' => 'Agente',
            'description' => 'demo',
            'status' => 'active',
            'prompt_configuration' => ['system_prompt' => 'Hola'],
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function inbound(Agent $agent, string $from, string $profile = ''): void
    {
        $this->post(route('api.agents.twilio.whatsapp', $agent), [
            'From' => 'whatsapp:'.$from,
            'To' => 'whatsapp:+573000000000',
            'Body' => 'Hola',
            'ProfileName' => $profile,
            'MessageSid' => 'SM'.uniqid(),
        ])->assertOk();
    }

    public function test_quien_escribe_por_primera_vez_queda_como_cliente(): void
    {
        $agent = $this->makeAgent();

        $this->inbound($agent, '+573101234567', 'Ana Pérez');

        $client = Client::where('agent_id', $agent->id)->first();
        $this->assertNotNull($client);
        $this->assertSame('573101234567', $client->phone);
        $this->assertSame('Ana Pérez', $client->name);
        $this->assertDatabaseHas('client_load_dates', [
            'client_id' => $client->id,
            'source' => ClientLoadDate::SOURCE_WHATSAPP,
        ]);
    }

    public function test_sin_nombre_de_perfil_usa_el_numero(): void
    {
        $agent = $this->makeAgent();

        $this->inbound($agent, '+573101234567');

        $this->assertSame('WhatsApp 573101234567', Client::where('agent_id', $agent->id)->value('name'));
    }

    public function test_no_duplica_un_cliente_existente_ni_en_mensajes_repetidos(): void
    {
        $agent = $this->makeAgent();
        // Cliente cargado antes con el número en otro formato (10 dígitos).
        Client::create([
            'agent_id' => $agent->id,
            'name' => 'Luis',
            'lastname' => 'Gómez',
            'email' => 'luis@example.com',
            'phone' => '3101234567',
        ]);

        $this->inbound($agent, '+573101234567', 'Luis G');
        $this->inbound($agent, '+573101234567', 'Luis G');
        $this->inbound($agent, '+573109999999', 'Otra');
        $this->inbound($agent, '+573109999999', 'Otra');

        $this->assertSame(2, Client::where('agent_id', $agent->id)->count());
        $this->assertSame('Luis', Client::where('phone', '3101234567')->value('name'), 'no pisa los datos existentes');
    }

    public function test_el_comando_crea_los_clientes_faltantes_y_es_repetible(): void
    {
        $agent = $this->makeAgent();
        Client::create([
            'agent_id' => $agent->id, 'name' => 'Luis', 'lastname' => 'G',
            'email' => 'l@example.com', 'phone' => '3101234567',
        ]);
        foreach ([['+573101234567', null], ['+573107777777', 'Marta'], ['573107777777', 'Marta']] as [$from, $profile]) {
            TwilioMessage::create([
                'agent_id' => $agent->id, 'channel' => 'whatsapp', 'from_number' => $from,
                'direction' => 'inbound', 'body' => 'Hola', 'profile_name' => $profile,
            ]);
        }

        $this->artisan('clients:backfill-from-messages', ['--dry-run' => true])
            ->expectsOutputToContain('Total que se crearían: 1')->assertSuccessful();
        $this->assertSame(1, Client::where('agent_id', $agent->id)->count(), 'dry-run no guarda');

        $this->artisan('clients:backfill-from-messages')->expectsOutputToContain('Total creados: 1')->assertSuccessful();
        $this->artisan('clients:backfill-from-messages')->expectsOutputToContain('Total creados: 0')->assertSuccessful();

        $this->assertSame(2, Client::where('agent_id', $agent->id)->count());
        $this->assertSame('Marta', Client::where('phone', '573107777777')->value('name'));
    }
}
