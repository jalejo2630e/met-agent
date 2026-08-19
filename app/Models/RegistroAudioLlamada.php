<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroAudioLlamada extends Model
{
    protected $table = 'registro_audio_llamadas';

    public $timestamps = false;

    protected $fillable = ['conversation_id', 'audio'];
}
