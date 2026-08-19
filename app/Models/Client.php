<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    public const STATUS_NO_CONTACTADO = 'no_contactado';

    public const STATUS_LLAMADA_PROGRAMADA = 'llamada_programada';

    protected $attributes = [
        'status' => self::STATUS_NO_CONTACTADO,
    ];

    protected $fillable = [
        'agent_id',
        'name',
        'lastname',
        'email',
        'phone',
        'document_type',
        'document',
        'custom_fields',
        'loaded_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'loaded_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function loadDates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientLoadDate::class)->orderByDesc('loaded_at');
    }

    public function contactLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientContactLog::class);
    }

    public function notes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientNote::class)->orderByDesc('created_at');
    }

    public function callbackRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientCallbackRequest::class)->orderByDesc('scheduled_date');
    }

    public function alertasLlamada(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AlertaLlamada::class)->orderByDesc('created_at');
    }

    public function formResponse(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AgentFormResponse::class);
    }

    /**
     * Actualiza el status del cliente según si tiene callbacks pendientes.
     */
    public function refreshCallbackStatus(): void
    {
        $hasPending = $this->callbackRequests()
            ->where('status', ClientCallbackRequest::STATUS_PENDING)
            ->exists();

        $this->update([
            'status' => $hasPending ? self::STATUS_LLAMADA_PROGRAMADA : self::STATUS_NO_CONTACTADO,
        ]);
    }

    /**
     * Clientes que tienen datos en custom_fields (recolección no vacía).
     * Compatible con PostgreSQL (jsonb), MySQL (json) y SQLite.
     */
    public function scopeWithCollectionData(Builder $query): Builder
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return $query->whereNotNull('custom_fields')
                ->whereRaw("custom_fields::text NOT IN ('{}', '[]')");
        }

        if ($driver === 'mysql') {
            return $query->whereNotNull('custom_fields')
                ->whereRaw('JSON_LENGTH(custom_fields) > 0');
        }

        return $query->whereNotNull('custom_fields')
            ->whereRaw("TRIM(COALESCE(custom_fields, '')) NOT IN ('', '{}', '[]')");
    }
}
