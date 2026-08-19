<?php

namespace App\Models;

use App\Observers\AgentObserver;
use App\Support\WhatsappConversationsConnection;
use Illuminate\Database\Eloquent\Model;

class AgentWhatsappConversation extends Model
{
    protected $fillable = [
        'session_id',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'message' => 'array',
        ];
    }

    public function getConnectionName()
    {
        return WhatsappConversationsConnection::eloquentConnectionName();
    }

    public function setTableForAgent(Agent $agent): static
    {
        $this->setTable(AgentObserver::getTableName($agent->id));

        return $this;
    }

    public static function forAgent(Agent $agent): static
    {
        return (new static)->setTable(AgentObserver::getTableName($agent->id));
    }
}
