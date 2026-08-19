<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentApiKey extends Model
{
    protected $fillable = [
        'agent_id',
        'name',
        'key_prefix',
        'key_hash',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public static function generate(string $agentId, string $name): array
    {
        $plainKey = 'ag_'.Str::random(40);
        $prefix = substr($plainKey, 0, 8);

        $apiKey = self::create([
            'agent_id' => $agentId,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => Hash::make($plainKey),
        ]);

        return ['api_key' => $apiKey, 'plain_key' => $plainKey];
    }

    public function matches(string $key): bool
    {
        return Hash::check($key, $this->key_hash);
    }
}
