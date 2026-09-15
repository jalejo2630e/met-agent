<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentPromptUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        // El 2FA es obligatorio (EnsureTwoFactorEnrolled), así que el usuario
        // debe tener el 2FA confirmado para poder acceder a las rutas.
        return User::factory()->create([
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function makeAgent(User $owner): Agent
    {
        return Agent::create([
            'name' => 'Agente Prueba',
            'description' => 'demo',
            'status' => 'active',
            'prompt_configuration' => [
                'system_prompt' => 'Prompt original.',
                'ai_provider' => 'openai',
                'ai_model' => 'gpt-4.1-mini',
                'sections' => ['debe', 'preservarse-o-no'],
            ],
            'user_id' => $owner->id,
        ]);
    }

    public function test_el_dueno_puede_actualizar_el_prompt_del_agente(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        $response = $this->actingAs($owner)->put(
            route('agents.prompt-config.update', $agent),
            [
                'system_prompt' => '  Nuevo prompt del sistema.  ',
                'ai_provider' => 'anthropic',
                'ai_model' => 'claude-sonnet-4-5',
            ]
        );

        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();

        $agent->refresh();
        $this->assertSame('Nuevo prompt del sistema.', $agent->prompt_configuration['system_prompt'], 'debe guardarse y hacer trim');
        $this->assertSame('anthropic', $agent->prompt_configuration['ai_provider']);
        $this->assertSame('claude-sonnet-4-5', $agent->prompt_configuration['ai_model']);
        $this->assertArrayNotHasKey('sections', $agent->prompt_configuration, 'sections debe eliminarse en el update rapido');
    }

    public function test_via_ruta_edit_completa_actualiza_prompt(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        $response = $this->actingAs($owner)->put(
            route('agents.update', $agent),
            [
                'name' => 'Agente Renombrado',
                'description' => 'nueva desc',
                'status' => 'draft',
                'prompt_configuration' => ['system_prompt' => 'Prompt via edit.'],
            ]
        );

        $response->assertSessionHasNoErrors();
        $agent->refresh();
        $this->assertSame('Agente Renombrado', $agent->name);
        $this->assertSame('Prompt via edit.', $agent->prompt_configuration['system_prompt']);
    }

    public function test_system_prompt_es_obligatorio(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        $response = $this->actingAs($owner)->put(
            route('agents.prompt-config.update', $agent),
            ['system_prompt' => '']
        );

        $response->assertSessionHasErrors('system_prompt');
    }

    public function test_sin_sesion_NO_guarda_y_redirige_a_login(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        // Sin actingAs => usuario sin sesión (sesión perdida/expirada).
        // Simulamos una petición Inertia (como la que hace router.put del front).
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->put(
            route('agents.prompt-config.update', $agent),
            ['system_prompt' => 'TEXTO NUEVO QUE EL USUARIO ESCRIBIO']
        );

        // Inertia convierte el 302->login en 409 con X-Inertia-Location, o 302 clásico.
        $this->assertContains($response->getStatusCode(), [302, 409]);
        $target = $response->headers->get('Location')
            ?: $response->headers->get('X-Inertia-Location');
        $this->assertStringContainsString('/login', (string) $target, 'debe mandar a login');

        // Lo importante: NO se guardó nada.
        $agent->refresh();
        $this->assertSame('Prompt original.', $agent->prompt_configuration['system_prompt']);
    }

    public function test_peticion_normal_sin_sesion_redirige_a_login(): void
    {
        $owner = $this->makeUser();
        $agent = $this->makeAgent($owner);

        // Petición web clásica (no-Inertia) sin sesión.
        $response = $this->put(
            route('agents.prompt-config.update', $agent),
            ['system_prompt' => 'nuevo']
        );

        $response->assertRedirect(route('login'));
        $agent->refresh();
        $this->assertSame('Prompt original.', $agent->prompt_configuration['system_prompt']);
    }

    public function test_un_usuario_ajeno_no_puede_actualizar(): void
    {
        $owner = $this->makeUser();
        $intruso = $this->makeUser();
        $agent = $this->makeAgent($owner);

        $response = $this->actingAs($intruso)->put(
            route('agents.prompt-config.update', $agent),
            ['system_prompt' => 'hackeado']
        );

        $response->assertForbidden();
        $agent->refresh();
        $this->assertSame('Prompt original.', $agent->prompt_configuration['system_prompt']);
    }
}
