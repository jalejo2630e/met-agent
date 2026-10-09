<?php

namespace App\Services;

/**
 * Asigna cada mensaje a una sede (Bogotá / Chía) según el número de WhatsApp
 * de Twilio que lo recibió. Los números se configuran en config/services.php.
 */
class SedeResolver
{
    /** Clave de sede para el número receptor ("whatsapp:+57...") o null si no coincide. */
    public static function fromNumber(?string $number): ?string
    {
        $digits = self::digits($number);
        if ($digits === '') {
            return null;
        }

        foreach ((array) config('services.twilio.sedes', []) as $key => $sede) {
            $configured = self::digits($sede['number'] ?? null);
            if ($configured !== '' && $configured === $digits) {
                return (string) $key;
            }
        }

        return null;
    }

    /** @return array<string, string> clave => etiqueta */
    public static function labels(): array
    {
        return collect((array) config('services.twilio.sedes', []))
            ->map(fn (array $sede) => (string) ($sede['label'] ?? ''))
            ->all();
    }

    private static function digits(?string $number): string
    {
        return preg_replace('/\D/', '', (string) $number) ?? '';
    }
}
