<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentKnowledgeDocument extends Model
{
    protected $fillable = [
        'agent_id',
        'title',
        'original_filename',
        'mime',
        'size',
        'content',
        'enabled',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
