<?php

use App\Http\Controllers\Api\CallAlertApiController;
use App\Http\Controllers\Api\CallbackRequestApiController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\CollectDataController;
use App\Http\Controllers\Api\TwilioMessageController;
use Illuminate\Support\Facades\Route;

/*
 * {agent} debe ser solo dígitos. Si en la URL queda algo como "1}" (sustitución incorrecta de {agent_id}),
 * sin esta restricción Laravel intenta resolver el modelo y Postgres falla al castear a bigint.
 */
Route::post('agents/{agent}/clients', ClientApiController::class)
    ->whereNumber('agent')
    ->name('api.agents.clients');
Route::match(['get', 'post'], 'agents/{agent}/clients/by-phone', [ClientApiController::class, 'showByPhone'])
    ->whereNumber('agent')
    ->name('api.agents.clients.by-phone');
Route::patch('agents/{agent}/clients/custom-fields', [ClientApiController::class, 'updateCustomFieldsByPhone'])
    ->whereNumber('agent')
    ->name('api.agents.clients.custom-fields');
Route::post('clients/exists-by-phone', [ClientApiController::class, 'existsByPhone'])
    ->name('api.agents.clients.exists-by-phone');
Route::post('clients/duplicate', [ClientApiController::class, 'duplicateClient'])
    ->name('api.clients.duplicate');

Route::post('agents/{agent}/collect-data', CollectDataController::class)
    ->whereNumber('agent')
    ->name('api.agents.collect-data');

Route::post('agents/{agent}/callback-requests', CallbackRequestApiController::class)
    ->whereNumber('agent')
    ->name('api.agents.callback-requests');

Route::post('agents/{agent}/call-alerts', CallAlertApiController::class)
    ->whereNumber('agent')
    ->name('api.agents.call-alerts');

/*
 * Webhook de Twilio (WhatsApp/SMS) → agente de IA NATIVO de Laravel (reemplaza n8n
 * para el canal de texto). Configura esta URL en Twilio "When a message comes in".
 * La firma se valida con TWILIO_AUTH_TOKEN si está definido.
 */
Route::post('agents/{agent}/twilio/whatsapp', TwilioMessageController::class)
    ->whereNumber('agent')
    ->name('api.agents.twilio.whatsapp');
