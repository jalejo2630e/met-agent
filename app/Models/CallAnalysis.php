<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado del análisis de IA de una llamada (Laravel AI SDK).
 *
 * Se guarda en una tabla local propia (no en registro_escrito_llamada) para
 * funcionar aunque las transcripciones vengan de Supabase en modo solo lectura.
 * Clave lógica: (agent_id, conversation_id).
 */
class CallAnalysis extends Model
{
    protected $fillable = [
        'agent_id',
        'conversation_id',
        'phone',
        'summary',
        'sentiment',
        'category_slug',
        'motivo',
        'resultado',
        'requires_alert',
        'alerta_llamada_id',
        'model',
        'data',
    ];

    protected $casts = [
        'requires_alert' => 'boolean',
        'data' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(AlertaLlamada::class, 'alerta_llamada_id');
    }
}
