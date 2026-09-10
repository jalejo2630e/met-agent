<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentExtractionVariable extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'label',
        'description',
        'type',
        'order',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
