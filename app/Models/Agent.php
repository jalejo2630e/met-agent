<?php

namespace App\Models;

use App\Observers\AgentObserver;
use App\Support\WhatsappConversationsConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agent extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'description',
        'status',
        'n8n_workflow_id',
        'n8n_webhook_url',
        'n8n_webhook_node_id',
        'n8n_prompt_node_id',
        'prompt_configuration',
        'workflow_configuration',
        'user_id',
    ];

    protected $appends = ['whatsapp_conversations_table'];

    protected function casts(): array
    {
        return [
            'prompt_configuration' => 'array',
            'workflow_configuration' => 'array',
        ];
    }

    public function getWhatsappConversationsTableAttribute(): string
    {
        if (WhatsappConversationsConnection::readsViaRest()) {
            return WhatsappConversationsConnection::supabaseTableNameForAgent($this->id);
        }

        return AgentObserver::getTableName($this->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(AgentEndpoint::class)->orderBy('order');
    }

    public function messageConfig(): HasOne
    {
        return $this->hasOne(AgentMessageConfig::class);
    }

    public function callConfig(): HasOne
    {
        return $this->hasOne(AgentCallConfig::class);
    }

    public function dataVariables(): HasMany
    {
        return $this->hasMany(AgentDataVariable::class)->orderBy('order');
    }

    public function knowledgeDocuments(): HasMany
    {
        return $this->hasMany(AgentKnowledgeDocument::class)->orderBy('order')->orderBy('id');
    }

    public function extractionVariables(): HasMany
    {
        return $this->hasMany(AgentExtractionVariable::class)->orderBy('order')->orderBy('id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(AgentApiKey::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function clientFields(): HasMany
    {
        return $this->hasMany(AgentClientField::class)->orderBy('order');
    }

    public function reportWidgets(): HasMany
    {
        return $this->hasMany(AgentReportWidget::class)->orderBy('order')->orderBy('id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AgentQuestion::class)->orderBy('order');
    }

    public function formResponses(): HasMany
    {
        return $this->hasMany(AgentFormResponse::class);
    }

    public function clientSourceEndpoints(): HasMany
    {
        return $this->hasMany(AgentClientSourceEndpoint::class)->orderBy('order');
    }

    public function clientContactLogs(): HasMany
    {
        return $this->hasMany(ClientContactLog::class);
    }

    public function getWhatsappConversationsTable(): string
    {
        if (WhatsappConversationsConnection::readsViaRest()) {
            return WhatsappConversationsConnection::supabaseTableNameForAgent($this->id);
        }

        return AgentObserver::getTableName($this->id);
    }

    public function whatsappConversations()
    {
        return AgentWhatsappConversation::forAgent($this)->orderBy('created_at');
    }

    public function registrosLlamada(): HasMany
    {
        return $this->hasMany(RegistroEscritoLlamada::class, 'agent_id', 'id');
    }

    public function contactQueues(): HasMany
    {
        return $this->hasMany(ContactQueue::class)->orderByDesc('created_at');
    }

    public function callbackRequests(): HasMany
    {
        return $this->hasMany(ClientCallbackRequest::class)->orderBy('scheduled_date');
    }

    public function hasN8nWorkflow(): bool
    {
        return ! empty($this->n8n_workflow_id);
    }
}
