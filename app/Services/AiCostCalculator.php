<?php

namespace App\Services;

/**
 * Estima el costo en USD de las operaciones de IA a partir del modelo y los
 * tokens (o los segundos de audio para transcripción). Los precios son
 * aproximados y configurables vía config/ai_pricing.php.
 */
class AiCostCalculator
{
    /**
     * Precios por 1M de tokens (input/output) para modelos de texto/visión, y
     * por minuto para modelos de audio. Usados como respaldo si no hay config.
     *
     * @var array<string, array{in?: float, out?: float, per_minute?: float}>
     */
    private const DEFAULT_PRICES = [
        'gpt-4.1-mini' => ['in' => 0.40, 'out' => 1.60],
        'gpt-4.1-nano' => ['in' => 0.10, 'out' => 0.40],
        'gpt-4.1' => ['in' => 2.00, 'out' => 8.00],
        'gpt-4o-mini' => ['in' => 0.15, 'out' => 0.60],
        'gpt-4o' => ['in' => 2.50, 'out' => 10.00],
        'gpt-4o-mini-transcribe' => ['in' => 1.25, 'out' => 5.00],
        'whisper-1' => ['per_minute' => 0.006],
    ];

    /**
     * @return array<string, array{in?: float, out?: float, per_minute?: float}>
     */
    private function prices(): array
    {
        return array_merge(self::DEFAULT_PRICES, (array) config('ai_pricing.models', []));
    }

    /**
     * Costo estimado en USD de una operación.
     */
    public function cost(?string $model, int $promptTokens = 0, int $completionTokens = 0, float $seconds = 0): float
    {
        $key = $this->normalize($model);
        $prices = $this->prices();
        $p = $prices[$key] ?? $prices['gpt-4.1-mini'];

        // Audio por minuto (p. ej. whisper-1).
        if (isset($p['per_minute'])) {
            return round(($seconds / 60) * (float) $p['per_minute'], 6);
        }

        $in = (float) ($p['in'] ?? 0);
        $out = (float) ($p['out'] ?? 0);

        return round(($promptTokens / 1_000_000) * $in + ($completionTokens / 1_000_000) * $out, 6);
    }

    private function normalize(?string $model): string
    {
        $m = strtolower(trim((string) $model));
        if ($m === '') {
            return 'gpt-4.1-mini';
        }
        // Quita sufijo de fecha, p. ej. gpt-4.1-mini-2025-04-14 -> gpt-4.1-mini.
        $m = preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $m) ?? $m;

        return $m;
    }
}
