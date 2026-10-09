<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLoadDate extends Model
{
    public const SOURCE_IMPORT = 'import';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_API = 'api';

    public const SOURCE_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'client_id',
        'agent_id',
        'loaded_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'loaded_at' => 'datetime',
        ];
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
