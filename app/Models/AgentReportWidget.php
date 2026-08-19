<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentReportWidget extends Model
{
    public const METRIC_RANGE_BUCKETS = 'range_buckets';

    public const METRIC_CLIENT_PROGRESS = 'client_progress';

    public const METRIC_TOPIC_PROGRESS = 'topic_progress';

    public const METRIC_VALUE_COUNTS = 'value_counts';

    public const METRIC_CALLS = 'calls';

    public const SOURCE_CUSTOM_FIELD = 'custom_field';

    public const SOURCE_CALLS = 'calls';

    protected $fillable = [
        'agent_id',
        'title',
        'metric',
        'source',
        'field_name',
        'chart_type',
        'config',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
