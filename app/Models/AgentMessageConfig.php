<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessageConfig extends Model
{
    protected $fillable = [
        'agent_id',
        'webhook_url',
        'schedule_config',
        'plantillas',
        'default_plantilla_id',
    ];

    protected function casts(): array
    {
        return [
            'schedule_config' => 'array',
            'plantillas' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Campos base del cliente que una variable de plantilla puede referenciar.
     */
    public const CLIENT_BASE_FIELDS = ['name', 'lastname', 'email', 'phone', 'document_type', 'document'];

    /**
     * Resuelve las variables dinámicas de una plantilla para un cliente concreto.
     * Devuelve una lista ordenada [{ name, value }] lista para enviar al webhook.
     *
     * @return list<array{name: string, value: string}>
     */
    public function resolvePlantillaVariables(?string $idPlantilla, Client $client, ?Agent $agent = null): array
    {
        if ($idPlantilla === null || $idPlantilla === '') {
            return [];
        }

        $plantillas = is_array($this->plantillas) ? $this->plantillas : [];
        $plantilla = collect($plantillas)->first(fn ($p) => ($p['id'] ?? null) === $idPlantilla);

        if (! $plantilla || empty($plantilla['has_variables']) || empty($plantilla['variables']) || ! is_array($plantilla['variables'])) {
            return [];
        }

        $base = [
            'name' => $client->name,
            'lastname' => $client->lastname,
            'email' => $client->email,
            'phone' => $client->phone,
            'document_type' => $client->document_type,
            'document' => $client->document,
        ];
        $custom = is_array($client->custom_fields) ? $client->custom_fields : [];

        $resolved = [];
        foreach ($plantilla['variables'] as $variable) {
            $name = trim((string) ($variable['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = $variable['source_type'] ?? null;
            $key = $variable['source_key'] ?? null;
            $value = null;

            if ($type === 'field' && in_array($key, self::CLIENT_BASE_FIELDS, true)) {
                $value = $base[$key] ?? null;
            } elseif ($type === 'custom_field' && $key !== null) {
                $value = $custom[$key] ?? null;
            } elseif ($type === 'special' && $key === 'form_url') {
                $agent ??= $this->agent;
                $value = $agent ? AgentFormResponse::urlForClient($agent, $client) : null;
            }

            $resolved[] = ['name' => $name, 'value' => $value !== null ? (string) $value : ''];
        }

        return $resolved;
    }
}
