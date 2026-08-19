<?php

namespace Tests\Feature;

use App\Services\TwilioContentService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioTemplateTest extends TestCase
{
    public function test_crea_plantilla_en_twilio_y_la_envia_a_aprobacion(): void
    {
        config([
            'services.twilio.account_sid' => 'ACxxxx',
            'services.twilio.auth_token' => 'secret',
        ]);

        Http::fake([
            '*/ApprovalRequests/whatsapp' => Http::response(['status' => 'received'], 201),
            '*' => Http::response(['sid' => 'HX123', 'friendly_name' => 'recordatorio_cita'], 201),
        ]);

        $result = app(TwilioContentService::class)->createTemplate([
            'friendly_name' => 'Recordatorio Cita',
            'language' => 'es',
            'body' => 'Hola {{1}}, tu cita es el {{2}}.',
            'category' => 'UTILITY',
            'submit_whatsapp' => true,
            'variables' => ['1' => 'nombre', '2' => 'fecha'],
        ]);

        $this->assertSame('HX123', $result['sid']);
        $this->assertTrue($result['approval']['ok']);

        // Se envió el contenido con el body y las variables.
        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/v1/Content')) {
                return false;
            }
            $body = $request->data();

            return ($body['friendly_name'] ?? null) === 'Recordatorio Cita'
                && ($body['types']['twilio/text']['body'] ?? null) === 'Hola {{1}}, tu cita es el {{2}}.'
                && ($body['variables']['1'] ?? null) === 'nombre';
        });
    }
}
