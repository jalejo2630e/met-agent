<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'n8n' => [
        'url' => rtrim((string) env('N8N_API_URL', ''), '/'),
        'api_key' => env('API_N8N'),
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
    ],

    /*
    | Twilio: canal de WhatsApp/SMS para el agente NATIVO de Laravel (reemplaza n8n
    | en el flujo de texto). auth_token valida la firma del webhook entrante.
    | Webhook a configurar en Twilio: POST /api/agents/{agent_id}/twilio/whatsapp
    */
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        // Código de país por defecto para formatear teléfonos a E.164 al enviar por WhatsApp.
        'default_country_code' => env('TWILIO_DEFAULT_COUNTRY_CODE', '57'),
    ],

    /*
    | API Supabase (misma URL/anon key que el frontend; útil para futuras llamadas REST desde PHP).
    | En PHP también se leen VITE_SUPABASE_* como respaldo si no existen SUPABASE_* sin prefijo.
    */
    'supabase' => [
        'url' => env('SUPABASE_URL', env('VITE_SUPABASE_URL')),
        'anon_key' => env('SUPABASE_ANON_KEY', env('VITE_SUPABASE_ANON_KEY', env('VITE_SUPABASE_KEY'))),
    ],

    /*
    | Registros de llamadas / transcripciones: solo lectura.
    | supabase: PostgREST (VITE_SUPABASE_URL + anon key). internal: Eloquent (DB_*; REGISTRO_LLAMADAS_DB_CONNECTION opcional).
    */
    'campana_ainoa' => env('CAMPANA_AINOA'),

    'registro_escrito_llamadas' => [
        'source' => env('CALL_TRANSCRIPTS_SOURCE', 'internal'),
        'connection' => env('REGISTRO_LLAMADAS_DB_CONNECTION'),
        'table' => env('REGISTRO_ESCRITO_LLAMADAS_TABLE', 'registro_escrito_llamada'),
        /* Supabase: columna text "campana". Local (migración): "campana_id" UUID. Vacío = autodetectar campana o campana_id. */
        'campana_column' => env('CAMPANA_AINOA_COLUMN', 'campana'),
    ],

    /* Audio de llamadas en Supabase (columna audio text base64). */
    'registro_audio_llamadas' => [
        'table' => env('REGISTRO_AUDIO_LLAMADAS_TABLE', 'registro_audio_llamadas'),
    ],

    /*
    | Conversaciones WhatsApp: solo lectura si el origen es Supabase (sin crear tablas aquí).
    | "internal" = DB_*; se crean tablas agent_{id}_whatsapp al crear agente.
    | Setting whatsapp_conversations_source tiene prioridad sobre WHATSAPP_CONVERSATIONS_SOURCE.
    | Patrón Supabase: {agent_id} se sustituye por el ID del agente (Ajustes o WHATSAPP_SUPABASE_TABLE_PATTERN).
    */
    'whatsapp_conversations' => [
        'source' => env('WHATSAPP_CONVERSATIONS_SOURCE', 'internal'),
        'connection' => env('WHATSAPP_CONVERSATIONS_DB_CONNECTION', 'supabase'),
        'supabase_table_pattern' => env('WHATSAPP_SUPABASE_TABLE_PATTERN', 'agent_{agent_id}_whatsapp_conversations'),
        /* Columna PostgREST para filtrar por teléfono del cliente (eq). session_id coincide con tablas proa/agent_*; phone si tu esquema usa ese nombre. */
        'supabase_phone_column' => env('WHATSAPP_SUPABASE_PHONE_COLUMN', 'session_id'),
        /* Columnas en SELECT PostgREST (sin espacios). Si no existe created_at en la tabla, no la incluyas. */
        'supabase_select_columns' => env('WHATSAPP_SUPABASE_SELECT_COLUMNS', 'id,message'),
        /* Columna de fecha para contar el consumo del mes (tope mensual). Debe existir en la tabla. */
        'supabase_date_column' => env('WHATSAPP_SUPABASE_DATE_COLUMN', 'created_at'),
    ],

];
