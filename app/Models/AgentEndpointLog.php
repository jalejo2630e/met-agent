<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEndpointLog extends Model
{
    protected $fillable = [
        'agent_endpoint_id',
        'source',
        'request_method',
        'request_url',
        'request_headers',
        'request_body',
        'response_status',
        'response_body',
        'error_message',
        'success',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'success' => 'boolean',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(AgentEndpoint::class, 'agent_endpoint_id');
    }
}
