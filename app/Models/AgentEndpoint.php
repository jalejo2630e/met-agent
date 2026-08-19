<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentEndpoint extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'url',
        'method',
        'headers',
        'client_parameter',
        'response_mapping',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'response_mapping' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AgentEndpointLog::class)->latest();
    }
}
