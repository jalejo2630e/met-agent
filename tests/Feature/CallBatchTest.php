<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentCallConfig;
use App\Models\Client;
use App\Models\ContactQueue;
use App\Models\ContactQueueCall;
use App\Models\User;
use App\Services\CallBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CallBatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgentWithClients(int $count): array
    {
        $user = User::factory()->create();
        $agent = Agent::forceCreate(['name' => 'Agente', 'status' => 'active', 'user_id' => $user->id]);
        AgentCallConfig::create([
            'agent_id' => $agent->id,
            'webhook_url' => 'https://hook.test/call',
            'elevenlabs_agent_id' => 'agent_x',
            'elevenlabs_phone_number_id' => 'phnum_x',
        ]);

        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $client = Client::forceCreate([
                'agent_id' => $agent->id,
                'name' => 'Cliente'.$i,
                'lastname' => 'Prueba',
                'email' => "cliente{$i}@example.com",
                'phone' => '30000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'document_type' => 'CC',
                'document' => '1000'.$i,
                'custom_fields' => ['ciudad' => 'Bogotá'],
            ]);
            $ids[] = $client->id;
        }

        return [$agent, $ids];
    }

    public function test_ventana_deslizante_mantiene_10_en_curso_y_libera_con_post_call(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        [$agent, $ids] = $this->makeAgentWithClients(25);

        $queue = ContactQueue::create([
            'agent_id' => $agent->id,
            'type' => ContactQueue::TYPE_CALL,
            'concurrency' => 10,
            'client_ids' => $ids,
            'status' => ContactQueue::STATUS_PENDING,
        ]);

        $service = app(CallBatchService::class);
        $service->start($queue->fresh());

        // Arranca exactamente 10 en curso; el resto pendientes.
        $this->assertSame(10, ContactQueueCall::where('contact_queue_id', $queue->id)->where('status', 'in_flight')->count());
        $this->assertSame(15, ContactQueueCall::where('contact_queue_id', $queue->id)->where('status', 'pending')->count());

        // El payload de la llamada incluye phone_number_id y variables dinámicas.
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://hook.test/call'
                && ($body['agent_id'] ?? null) === 'agent_x'
                && ($body['phone_number_id'] ?? null) === 'phnum_x'
                && ! empty($body['dynamic_variables']['client_id'])
                && isset($body['dynamic_variables']['ciudad']);
        });

        // Llega el post-call de una llamada → se libera y entra la siguiente (sync queue).
        $first = ContactQueueCall::where('contact_queue_id', $queue->id)->where('status', 'in_flight')->orderBy('id')->first();
        $service->handlePostCall($agent->id, 'conv-1', $first->phone, $first->id);

        $this->assertSame(1, ContactQueueCall::where('contact_queue_id', $queue->id)->where('status', 'done')->count());
        $this->assertSame(10, ContactQueueCall::where('contact_queue_id', $queue->id)->where('status', 'in_flight')->count());
        $this->assertSame(14, ContactQueueCall::where('contact_queue_id', $queue->id)->where('status', 'pending')->count());
    }
}
