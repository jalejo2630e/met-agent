<?php

namespace App\Observers;

use App\Models\Agent;
use App\Support\DefaultReportWidgets;
use App\Support\WhatsappConversationsConnection;
use Illuminate\Support\Facades\Schema;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        $this->createWhatsappConversationsTable($agent->id);
        DefaultReportWidgets::seedFor($agent);
    }

    public function deleted(Agent $agent): void
    {
        $this->dropWhatsappConversationsTable($agent->id);
    }

    protected function createWhatsappConversationsTable(int $agentId): void
    {
        if (! WhatsappConversationsConnection::managesLocalTables()) {
            return;
        }

        $tableName = $this->getTableName($agentId);
        $connection = WhatsappConversationsConnection::eloquentConnectionName();

        if (Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->id();
            $table->string('session_id', 255);
            $table->json('message');
            $table->timestamps();
        });
    }

    protected function dropWhatsappConversationsTable(int $agentId): void
    {
        if (! WhatsappConversationsConnection::managesLocalTables()) {
            return;
        }

        $tableName = $this->getTableName($agentId);
        Schema::connection(WhatsappConversationsConnection::eloquentConnectionName())->dropIfExists($tableName);
    }

    public static function getTableName(int $agentId): string
    {
        return "agent_{$agentId}_whatsapp_conversations";
    }
}
