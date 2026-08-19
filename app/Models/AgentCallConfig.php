<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCallConfig extends Model
{
    protected $fillable = [
        'agent_id',
        'webhook_url',
        'elevenlabs_agent_id',
        'prompt_configuration',
        'schedule_config',
    ];

    protected function casts(): array
    {
        return [
            'prompt_configuration' => 'array',
            'schedule_config' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
