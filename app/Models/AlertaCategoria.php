<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertaCategoria extends Model
{
    protected $table = 'alerta_categorias';

    protected $fillable = [
        'nombre',
        'slug',
        'color',
    ];

    public function alertasLlamada(): HasMany
    {
        return $this->hasMany(AlertaLlamada::class, 'alerta_categoria_id');
    }
}
