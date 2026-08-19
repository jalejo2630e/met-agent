<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaLlamada extends Model
{
    protected $table = 'alertas_llamada';

    protected $fillable = [
        'client_id',
        'agent_id',
        'alerta_categoria_id',
        'phone',
        'numero_llamada',
        'paso_llamada',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'numero_llamada' => 'integer',
            'paso_llamada' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AlertaCategoria::class, 'alerta_categoria_id');
    }
}
