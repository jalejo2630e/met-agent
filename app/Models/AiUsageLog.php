<?php

namespace App\Models;

use App\Services\AiCostCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'agent_id',
        'from_number',
        'kind',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'seconds',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'seconds' => 'float',
            'cost' => 'float',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Registra una operación de IA calculando su costo estimado. Best-effort:
     * nunca lanza para no romper el flujo de mensajes.
     */
    public static function record(int $agentId, ?string $from, string $kind, ?string $model, int $promptTokens = 0, int $completionTokens = 0, float $seconds = 0): void
    {
        try {
            $cost = app(AiCostCalculator::class)->cost($model, $promptTokens, $completionTokens, $seconds);

            static::create([
                'agent_id' => $agentId,
                'from_number' => $from,
                'kind' => $kind,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'seconds' => $seconds,
                'cost' => $cost,
            ]);
        } catch (\Throwable) {
            // no-op
        }
    }
}
