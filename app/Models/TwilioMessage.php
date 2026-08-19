<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de mensajes de texto (WhatsApp/SMS) atendidos por el agente nativo
 * de Laravel vía el webhook de Twilio. Da memoria a la conversación por número
 * y permite mostrar el historial en el panel.
 */
class TwilioMessage extends Model
{
    protected $fillable = [
        'agent_id',
        'channel',
        'from_number',
        'direction',
        'body',
        'message_sid',
        'profile_name',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
