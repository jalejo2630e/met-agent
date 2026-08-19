<?php

namespace App\Support;

/**
 * Convierte una transcripción almacenada (JSON [{role,message}] o texto plano)
 * a un texto de conversación legible para enviar a un modelo de IA.
 *
 * Reutilizable por el análisis de llamadas y por cualquier otro consumidor.
 */
class TranscriptNormalizer
{
    /**
     * @return array<int, array{role: string, message: string}>
     */
    public static function toTurns(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $turns = [];
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $role = (string) ($item['role'] ?? $item['type'] ?? 'user');
                $message = (string) ($item['message'] ?? $item['content'] ?? $item['text'] ?? '');
                $message = trim($message);
                if ($message !== '') {
                    $turns[] = ['role' => $role, 'message' => $message];
                }
            }

            if ($turns !== []) {
                return $turns;
            }
        }

        // Texto plano: un único turno.
        return [['role' => 'user', 'message' => $raw]];
    }

    /**
     * Devuelve la transcripción como texto plano "Rol: mensaje" por línea.
     */
    public static function toText(?string $raw): string
    {
        $turns = self::toTurns($raw);

        return implode("\n", array_map(
            static fn (array $t): string => ucfirst($t['role']).': '.$t['message'],
            $turns,
        ));
    }
}
