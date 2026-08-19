<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class WhatsappConversationsConnection
{
    /**
     * Patrón de nombre de tabla en Supabase (PostgREST).
     * Si incluye {agent_id}, se sustituye por el ID del agente; si no, el valor es el nombre fijo de la tabla (una sola tabla para todos).
     * Prioridad: ajuste en BD → WHATSAPP_SUPABASE_TABLE_PATTERN / config.
     */
    public static function supabaseTablePattern(): string
    {
        $fromDb = Setting::get('whatsapp_conversations_supabase_table_pattern');
        if (is_string($fromDb)) {
            $trimmed = trim($fromDb);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return (string) config('services.whatsapp_conversations.supabase_table_pattern', 'agent_{agent_id}_whatsapp_conversations');
    }

    /**
     * Nombre de tabla en Supabase: con {agent_id} en el patrón se sustituye; sin marcador se usa el patrón tal cual (tabla única).
     */
    public static function supabaseTableNameForAgent(int $agentId): string
    {
        $pattern = self::supabaseTablePattern();
        $name = str_contains($pattern, '{agent_id}')
            ? str_replace('{agent_id}', (string) $agentId, $pattern)
            : $pattern;

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            return 'agent_'.$agentId.'_whatsapp_conversations';
        }

        return $name;
    }

    /**
     * Columna en la tabla Supabase usada para filtrar mensajes por teléfono (valor normalizado a dígitos).
     * Prioridad: ajuste en BD → WHATSAPP_SUPABASE_PHONE_COLUMN / config (por defecto session_id).
     */
    public static function supabasePhoneFilterColumn(): string
    {
        $fromDb = Setting::get('whatsapp_conversations_supabase_phone_column');
        if (is_string($fromDb)) {
            $trimmed = trim($fromDb);
            if ($trimmed !== '') {
                $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $trimmed) ?? '';
                if ($sanitized !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $sanitized)) {
                    return $sanitized;
                }
            }
        }

        $fallback = (string) config('services.whatsapp_conversations.supabase_phone_column', 'session_id');
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $fallback) ?? '';

        return ($sanitized !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $sanitized))
            ? $sanitized
            : 'session_id';
    }

    /**
     * Columna de fecha (Supabase) usada para contar el consumo del mes calendario (tope mensual).
     * Prioridad: ajuste en BD → WHATSAPP_SUPABASE_DATE_COLUMN / config (por defecto created_at).
     */
    public static function supabaseDateColumn(): string
    {
        $fromDb = Setting::get('whatsapp_conversations_supabase_date_column');
        if (is_string($fromDb)) {
            $trimmed = trim($fromDb);
            if ($trimmed !== '') {
                $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $trimmed) ?? '';
                if ($sanitized !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $sanitized)) {
                    return $sanitized;
                }
            }
        }

        $fallback = (string) config('services.whatsapp_conversations.supabase_date_column', 'created_at');
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $fallback) ?? '';

        return ($sanitized !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $sanitized))
            ? $sanitized
            : 'created_at';
    }

    /**
     * Lista select= de PostgREST (columnas separadas por coma, solo identificadores seguros).
     */
    public static function supabaseSelectColumns(): string
    {
        $fromDb = Setting::get('whatsapp_conversations_supabase_select_columns');
        $raw = '';
        if (is_string($fromDb) && trim($fromDb) !== '') {
            $raw = trim($fromDb);
        } else {
            $raw = (string) config('services.whatsapp_conversations.supabase_select_columns', 'id,message');
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $safe = [];
        foreach ($parts as $p) {
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $p)) {
                $safe[] = $p;
            }
        }

        return $safe !== [] ? implode(',', $safe) : 'id,message';
    }

    /**
     * internal = BD de la app (se crean tablas por agente).
     * supabase = lectura vía API REST (VITE_SUPABASE_URL + anon key), sin PDO.
     */
    public static function source(): string
    {
        $fallback = config('services.whatsapp_conversations.source', 'internal');
        if (! Schema::hasTable('settings')) {
            return $fallback === 'supabase' ? 'supabase' : 'internal';
        }

        $s = Setting::get('whatsapp_conversations_source') ?? $fallback;

        return $s === 'supabase' ? 'supabase' : 'internal';
    }

    public static function readsViaRest(): bool
    {
        return self::source() === 'supabase';
    }

    /**
     * Si es false, no se crean ni eliminan tablas agent_*_whatsapp (origen externo / Supabase REST).
     */
    public static function managesLocalTables(): bool
    {
        return self::source() === 'internal';
    }

    /**
     * Conexión Eloquent solo para WhatsApp interno.
     */
    public static function eloquentConnectionName(): string
    {
        return (string) config('database.default');
    }
}
