<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentFormAnswer extends Model
{
    protected $fillable = [
        'agent_form_response_id',
        'agent_question_id',
        'question_label',
        'answer',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(AgentFormResponse::class, 'agent_form_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AgentQuestion::class, 'agent_question_id');
    }
}
