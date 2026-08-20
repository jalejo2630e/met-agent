<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro crudo de cada evento recibido en el webhook Post-Call de ElevenLabs.
 * El audio NO se guarda aquí (vive en registro_audio_llamadas); se enlaza por
 * conversation_id y se carga bajo demanda en la vista de logs.
 */
class PostCallWebhookLog extends Model
{
    protected $fillable = [
        'agent_id',
        'conversation_id',
        'phone',
        'event_type',
        'status',
        'call_successful',
        'duration_secs',
        'cost',
        'has_audio',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'has_audio' => 'boolean',
        'cost' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
