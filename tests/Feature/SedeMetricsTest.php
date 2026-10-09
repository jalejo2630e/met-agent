<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\TwilioMessage;
use App\Models\User;
use App\Services\SedeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedeMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.auth_token' => '', // sin validación de firma en tests
            'services.twilio.sedes.bogota.number' => '+573001111111',
            'services.twilio.sedes.chia.number' => '+573002222222',
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function makeAgent(User $owner): Agent
    {
        return Agent::create([
            'name' => 'MET',
            'description' => 'demo',
            'status' => 'active',
            'prompt_configuration' => ['system_prompt' => 'Hola'],
            'user_id' => $owner->id,
        ]);
    }

    private function inbound(Agent $agent, string $from, string $to): void
    {
        $this->post(route('api.agents.twilio.whatsapp', $agent), [
            'From' => 'whatsapp:'.$from,
            'To' => 'whatsapp:'.$to,
            'Body' => 'Hola',
            'MessageSid' => 'SM'.uniqid(),
        ])->assertOk();
    }

    public function test_el_resolver_asigna_la_sede_segun_el_numero_receptor(): void
    {
        $this->assertSame('bogota', SedeResolver::fromNumber('whatsapp:+573001111111'));
        $this->assertSame('chia', SedeResolver::fromNumber('573002222222'));
        $this->assertNull(SedeResolver::fromNumber('+573009999999'));
        $this->assertNull(SedeResolver::fromNumber(null));
    }

    public function test_el_webhook_guarda_la_sede_del_mensaje(): void
    {
        $agent = $this->makeAgent($this->makeUser());

        $this->inbound($agent, '+573101000001', '+573001111111');
        $this->inbound($agent, '+573101000002', '+573002222222');

        $this->assertSame('bogota', TwilioMessage::where('from_number', '+573101000001')->value('sede'));
        $this->assertSame('chia', TwilioMessage::where('from_number', '+573101000002')->value('sede'));
    }

    public function test_una_respuesta_humana_hereda_la_sede_de_la_conversacion(): void
    {
        $agent = $this->makeAgent($this->makeUser());
        $this->inbound($agent, '+573101000003', '+573002222222');

        $this->assertSame('chia', TwilioMessage::sedeFor($agent->id, '573101000003'));
    }

    public function test_las_metricas_se_separan_por_sede(): void
    {
        $user = $this->makeUser();
        $agent = $this->makeAgent($user);

        // Bogotá: 2 personas (una escribe dos veces). Chía: 1 persona.
        $this->inbound($agent, '+573101000001', '+573001111111');
        $this->inbound($agent, '+573101000001', '+573001111111');
        $this->inbound($agent, '+573101000002', '+573001111111');
        $this->inbound($agent, '+573101000003', '+573002222222');

        $response = $this->actingAs($user)->getJson(route('agents.sede-metrics.index', $agent));
        $response->assertOk();

        $sedes = collect($response->json('sedes'))->keyBy('sede');
        $this->assertSame(2, $sedes['bogota']['unique_contacts']);
        $this->assertSame(3, $sedes['bogota']['inbound']);
        $this->assertSame(2, $sedes['bogota']['new_contacts']);
        $this->assertSame(1, $sedes['chia']['unique_contacts']);
        $this->assertSame(1, $sedes['chia']['inbound']);
        $this->assertFalse($sedes->has(''), 'sin actividad sin sede no debe aparecer');
    }
}
