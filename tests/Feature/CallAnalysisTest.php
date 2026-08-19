<?php

namespace Tests\Feature;

use App\Ai\Agents\CallAnalyst;
use App\Models\Agent;
use App\Models\AlertaCategoria;
use App\Models\User;
use App\Services\CallAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_analiza_una_llamada_y_genera_resumen_y_alerta(): void
    {
        // La categoría "problema" ya viene sembrada por la migración; aseguramos su existencia.
        AlertaCategoria::firstOrCreate(['slug' => 'problema'], ['nombre' => 'Problema', 'color' => '#ef4444']);

        $user = User::factory()->create();
        $agent = Agent::forceCreate([
            'name' => 'Agente Prueba',
            'status' => 'active',
            'user_id' => $user->id,
            'prompt_configuration' => ['system_prompt' => 'Eres un asistente.'],
        ]);

        // Respuesta estructurada simulada del proveedor de IA.
        CallAnalyst::fake([[
            'resumen' => 'El cliente reportó un problema con su cita médica y quedó pendiente.',
            'sentimiento' => 'negativo',
            'motivo_contacto' => 'Reagendar cita',
            'resultado' => 'pendiente',
            'requiere_alerta' => true,
            'categoria_alerta' => 'problema',
            'descripcion_alerta' => 'Cliente molesto por cita cancelada sin aviso.',
        ]]);

        $analysis = app(CallAnalysisService::class)->analyze(
            $agent,
            'conv-123',
            "Agente: Buenos días\nUsuario: Tengo un problema con mi cita",
            '3001234567',
        );

        $this->assertDatabaseHas('call_analyses', [
            'agent_id' => $agent->id,
            'conversation_id' => 'conv-123',
            'sentiment' => 'negativo',
            'requires_alert' => true,
            'category_slug' => 'problema',
        ]);

        $this->assertNotNull($analysis->alerta_llamada_id, 'Debe crear una AlertaLlamada.');
        $this->assertDatabaseHas('alertas_llamada', [
            'agent_id' => $agent->id,
            'id' => $analysis->alerta_llamada_id,
        ]);

        CallAnalyst::assertPromptedTimes(1);
    }

    public function test_no_crea_alerta_cuando_no_se_requiere(): void
    {
        $user = User::factory()->create();
        $agent = Agent::forceCreate([
            'name' => 'Agente Prueba 2',
            'status' => 'active',
            'user_id' => $user->id,
            'prompt_configuration' => ['system_prompt' => 'Eres un asistente.'],
        ]);

        CallAnalyst::fake([[
            'resumen' => 'Llamada informativa resuelta sin novedad.',
            'sentimiento' => 'positivo',
            'motivo_contacto' => 'Consulta de horario',
            'resultado' => 'resuelto',
            'requiere_alerta' => false,
            'categoria_alerta' => 'ninguna',
            'descripcion_alerta' => '',
        ]]);

        $analysis = app(CallAnalysisService::class)->analyze($agent, 'conv-ok', 'Usuario: ¿A qué hora abren?');

        $this->assertNull($analysis->alerta_llamada_id);
        $this->assertFalse($analysis->requires_alert);
        $this->assertDatabaseCount('alertas_llamada', 0);
    }
}
