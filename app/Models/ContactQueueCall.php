<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ítem de llamada dentro de una ContactQueue de tipo "call".
 * Permite la ventana deslizante de concurrencia: cada ítem pasa por
 * pending → in_flight (llamada enviada al webhook) → done (llegó el post-call)
 * y así se liberan las siguientes llamadas.
 */
class ContactQueueCall extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_FLIGHT = 'in_flight';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMED_OUT = 'timed_out';

    protected $fillable = [
        'contact_queue_id',
        'agent_id',
        'client_id',
        'phone',
        'status',
        'conversation_id',
        'dispatched_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(ContactQueue::class, 'contact_queue_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
