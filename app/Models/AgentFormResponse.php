<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentFormResponse extends Model
{
    protected $fillable = [
        'agent_id',
        'client_id',
        'token',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AgentFormAnswer::class);
    }

    /**
     * Genera un token único y no adivinable para el enlace público.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    /**
     * URL pública del formulario para un cliente. Crea el enlace si no existe.
     * Devuelve null si el agente no tiene preguntas configuradas (no hay formulario que enviar).
     */
    public static function urlForClient(Agent $agent, Client $client): ?string
    {
        if (! $agent->questions()->exists()) {
            return null;
        }

        $response = static::where('agent_id', $agent->id)
            ->where('client_id', $client->id)
            ->first();

        if (! $response) {
            $response = static::create([
                'agent_id' => $agent->id,
                'client_id' => $client->id,
                'token' => static::generateToken(),
            ]);
        }

        return route('public.form.show', $response->token);
    }
}
