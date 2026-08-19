<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de lectura de transcripciones de llamada.
 * supabase = API REST (VITE_SUPABASE_URL + anon key), sin PDO.
 * internal = Eloquent sobre la BD de la aplicación (DB_*).
 */
class CallTranscriptsConnection
{
    public static function source(): string
    {
        if (Schema::hasTable('settings')) {
            $fromDb = Setting::get('call_transcripts_source');
            if ($fromDb === 'internal' || $fromDb === 'supabase') {
                return $fromDb;
            }
        }

        $envSource = config('services.registro_escrito_llamadas.source', 'internal');

        return ($envSource === 'supabase') ? 'supabase' : 'internal';
    }

    /**
     * Leer registros desde Supabase PostgREST (no usar Eloquent/PDO para eso).
     */
    public static function usesRest(): bool
    {
        return self::source() === 'supabase';
    }

    /**
     * Conexión Eloquent para registro_escrito_llamada solo cuando el origen es la BD interna.
     */
    public static function eloquentConnectionName(): string
    {
        return (string) config('database.default');
    }
}
