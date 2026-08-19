<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentClientSourceEndpoint extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'url',
        'headers',
        'schedule_time',
        'schedule_timezone',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
