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
        'sede',
        'from_number',
        'to_number',
        'direction',
        'body',
        'media_url',
        'media_type',
        'message_sid',
        'profile_name',
    ];

    /** Sede de la conversación: la del último mensaje entrante de ese número. */
    public static function sedeFor(int $agentId, string $from): ?string
    {
        $digits = preg_replace('/\D/', '', $from);

        return static::where('agent_id', $agentId)
            ->whereIn('from_number', [$digits, '+'.$digits])
            ->where('direction', 'inbound')
            ->whereNotNull('sede')
            ->orderByDesc('id')
            ->value('sede');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
