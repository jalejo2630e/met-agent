<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class RegistroEscritoLlamada extends Model
{
    public $timestamps = true;

    const UPDATED_AT = null;

    /**
     * Solo se usa cuando el origen de transcripciones es la BD interna (no Supabase REST).
     */
    public function getConnectionName()
    {
        return (string) config('database.default');
    }

    protected $fillable = [
        'agent_id',
        'campana',
        'campana_id',
        'conversation_id',
        'status',
        'transcript',
        'phone',
        'summary',
        'costo',
        'duration_call_seg',
        'variables_extraidas',
    ];

    public function getTable(): string
    {
        return config('services.registro_escrito_llamadas.table', 'registro_escrito_llamada');
    }

    /**
     * Filtra por valor de campaña explícito (p. ej. enviado en query ?campana=).
     */
    public function scopeForCampanaValue(Builder $query, ?string $campanaValue): Builder
    {
        $campanaValue = $campanaValue !== null ? trim($campanaValue) : '';
        if ($campanaValue === '') {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $conn = $query->getModel()->getConnectionName();
        $schema = Schema::connection($conn);

        $col = config('services.registro_escrito_llamadas.campana_column');
        $col = is_string($col) ? trim($col) : '';
        if ($col === '') {
            $col = $schema->hasColumn($table, 'campana')
                ? 'campana'
                : ($schema->hasColumn($table, 'campana_id') ? 'campana_id' : null);
        } elseif (! $schema->hasColumn($table, $col)) {
            $col = $schema->hasColumn($table, 'campana')
                ? 'campana'
                : ($schema->hasColumn($table, 'campana_id') ? 'campana_id' : null);
        }

        if (! $col || ! $schema->hasColumn($table, $col)) {
            return $query;
        }

        // La columna puede guardar solo el id o texto más largo (p. ej. JSON); alinear con
        // `campana like '%uuid%'`. Se castea a text porque campana_id es uuid (LIKE no
        // aplica a uuid en Postgres). Además se incluyen las filas SIN campaña: las
        // llamadas del webhook post-call nativo no traen campana_id y deben aparecer igual.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $campanaValue);

        return $query->where(function (Builder $q) use ($col, $escaped) {
            $q->whereRaw('"'.$col.'"::text like ?', ['%'.$escaped.'%'])
                ->orWhereNull($col);
        });
    }

    /**
     * Filtra por campaña desde CAMPANA_AINOA en config.
     */
    public function scopeForCampanaConfig(Builder $query): Builder
    {
        $v = config('services.campana_ainoa');

        return $query->forCampanaValue(is_string($v) ? $v : null);
    }

    protected function casts(): array
    {
        return [
            'agent_id' => 'string',
            'costo' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
