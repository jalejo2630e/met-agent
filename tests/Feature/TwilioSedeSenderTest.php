<?php

namespace Tests\Feature;

use App\Services\TwilioContentService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioSedeSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.twilio.account_sid' => 'ACxxxx',
            'services.twilio.auth_token' => 'secret',
            'services.twilio.whatsapp_from' => 'whatsapp:+573000000000',
            'services.twilio.sedes.bogota.number' => '+573001111111',
            'services.twilio.sedes.chia.number' => '+573002222222',
        ]);
        Http::fake(['*' => Http::response(['sid' => 'SM1'], 201)]);
    }

    private function lastFrom(): string
    {
        $from = '';
        Http::assertSent(function ($request) use (&$from) {
            $from = (string) ($request->data()['From'] ?? '');

            return true;
        });

        return $from;
    }

    public function test_responde_desde_el_numero_de_la_sede(): void
    {
        app(TwilioContentService::class)->sendWhatsappText('573101234567', 'Hola', sede: 'chia');

        $this->assertSame('whatsapp:+573002222222', $this->lastFrom());
    }

    public function test_sin_sede_usa_el_numero_general(): void
    {
        app(TwilioContentService::class)->sendWhatsappText('573101234567', 'Hola');

        $this->assertSame('whatsapp:+573000000000', $this->lastFrom());
    }

    public function test_sede_sin_numero_configurado_cae_al_general(): void
    {
        config(['services.twilio.sedes.chia.number' => null]);

        app(TwilioContentService::class)->sendWhatsappText('573101234567', 'Hola', sede: 'chia');

        $this->assertSame('whatsapp:+573000000000', $this->lastFrom());
    }

    public function test_puede_enviar_si_solo_hay_numeros_de_sede(): void
    {
        config(['services.twilio.whatsapp_from' => null]);
        $twilio = app(TwilioContentService::class);

        $this->assertTrue($twilio->canSendWhatsapp('bogota'));
        $this->assertFalse($twilio->canSendWhatsapp());
    }
}
