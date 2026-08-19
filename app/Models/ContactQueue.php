<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactQueue extends Model
{
    public const TYPE_CALL = 'call';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'agent_id',
        'type',
        'client_ids',
        'client_selection_rules',
        'status',
        'schedule_snapshot',
        'next_run_at',
        'last_run_at',
        'processed_count',
        'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'client_ids' => 'array',
            'client_selection_rules' => 'array',
            'schedule_snapshot' => 'array',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function totalCount(): int
    {
        if ($this->usesRulesForSelection()) {
            return 0; // Se calcula en tiempo de ejecución
        }

        return count($this->client_ids ?? []);
    }

    public function usesRulesForSelection(): bool
    {
        $rules = $this->client_selection_rules ?? [];

        return ! empty($rules);
    }

    public function isRecurring(): bool
    {
        $snap = $this->schedule_snapshot ?? [];
        if (isset($snap['is_recurring'])) {
            return (bool) $snap['is_recurring'];
        }

        return ! empty($snap['times']) && ! empty($snap['days_of_week']);
    }
}
