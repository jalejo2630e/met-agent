<?php

use App\Http\Controllers\AgentConfigController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentEndpointController;
use App\Http\Controllers\AgentFormController;
use App\Http\Controllers\AgentKnowledgeBaseController;
use App\Http\Controllers\AgentReportController;
use App\Http\Controllers\AgentReportWidgetController;
use App\Http\Controllers\AiCostController;
use App\Http\Controllers\SedeMetricsController;
use App\Http\Controllers\CallAnalysisController;
use App\Http\Controllers\ClientCallbackRequestController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CustomReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\PostCallLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\SendAgentReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TwilioInboxController;
use App\Http\Controllers\TwilioTemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('agents', AgentController::class)->except(['destroy']);

    // Configuración del agente (solo administrador: webhooks, endpoints, fuente de clientes)
    Route::middleware('role:administrador')->group(function () {
        Route::post('agents/{agent}/endpoints', [AgentConfigController::class, 'storeEndpoint'])->name('agents.endpoints.store');
        Route::post('agents/{agent}/endpoints/{endpoint}/test', [AgentEndpointController::class, 'test'])->name('agents.endpoints.test');
        Route::delete('agents/{agent}/endpoints/{endpoint}', [AgentConfigController::class, 'destroyEndpoint'])->name('agents.endpoints.destroy');
        Route::patch('agents/{agent}/endpoints/{endpoint}', [AgentEndpointController::class, 'update'])->name('agents.endpoints.update');
        Route::post('agents/{agent}/client-source-endpoints', [AgentConfigController::class, 'storeClientSourceEndpoint'])->name('agents.client-source-endpoints.store');
        Route::put('agents/{agent}/client-source-endpoints/{clientSourceEndpoint}', [AgentConfigController::class, 'updateClientSourceEndpoint'])->name('agents.client-source-endpoints.update')->scopeBindings();
        Route::delete('agents/{agent}/client-source-endpoints/{clientSourceEndpoint}', [AgentConfigController::class, 'destroyClientSourceEndpoint'])->name('agents.client-source-endpoints.destroy')->scopeBindings();
    });

    Route::put('agents/{agent}/message-config', [AgentConfigController::class, 'updateMessageConfig'])->name('agents.message-config.update');

    // Análisis de llamada con IA (Laravel AI SDK): resumen + clasificación + alerta automática
    Route::post('agents/{agent}/call-analysis', [CallAnalysisController::class, 'analyze'])
        ->whereNumber('agent')
        ->name('agents.call-analysis.analyze');

    // Plantillas de WhatsApp en Twilio (Content API)
    Route::get('agents/{agent}/twilio/templates', [TwilioTemplateController::class, 'index'])
        ->whereNumber('agent')
        ->name('agents.twilio.templates.index');
    Route::post('agents/{agent}/twilio/templates', [TwilioTemplateController::class, 'store'])
        ->whereNumber('agent')
        ->name('agents.twilio.templates.store');

    // Bandeja de mensajes recibidos por el webhook de Twilio
    Route::get('agents/{agent}/twilio/inbox', [TwilioInboxController::class, 'index'])
        ->whereNumber('agent')
        ->name('agents.twilio.inbox.index');
    Route::get('agents/{agent}/twilio/inbox/thread', [TwilioInboxController::class, 'thread'])
        ->whereNumber('agent')
        ->name('agents.twilio.inbox.thread');
    Route::post('agents/{agent}/twilio/inbox/extract', [TwilioInboxController::class, 'extract'])
        ->whereNumber('agent')
        ->name('agents.twilio.inbox.extract');
    // Toma de control humano desde la bandeja
    Route::post('agents/{agent}/twilio/inbox/pause', [TwilioInboxController::class, 'pause'])
        ->whereNumber('agent')->name('agents.twilio.inbox.pause');
    Route::post('agents/{agent}/twilio/inbox/reply', [TwilioInboxController::class, 'reply'])
        ->whereNumber('agent')->name('agents.twilio.inbox.reply');
    Route::post('agents/{agent}/twilio/inbox/template', [TwilioInboxController::class, 'sendTemplate'])
        ->whereNumber('agent')->name('agents.twilio.inbox.template');
    Route::post('agents/{agent}/twilio/inbox/media', [TwilioInboxController::class, 'sendMedia'])
        ->whereNumber('agent')->name('agents.twilio.inbox.media');
    Route::delete('agents/{agent}/twilio/inbox/conversation', [TwilioInboxController::class, 'clear'])
        ->whereNumber('agent')->middleware('role:administrador')->name('agents.twilio.inbox.clear');
    Route::get('agents/{agent}/twilio/inbox/notes', [TwilioInboxController::class, 'notes'])
        ->whereNumber('agent')->name('agents.twilio.inbox.notes.index');
    Route::post('agents/{agent}/twilio/inbox/notes', [TwilioInboxController::class, 'storeNote'])
        ->whereNumber('agent')->name('agents.twilio.inbox.notes.store');
    Route::delete('agents/{agent}/twilio/inbox/notes/{note}', [TwilioInboxController::class, 'destroyNote'])
        ->whereNumber('agent')->name('agents.twilio.inbox.notes.destroy')->scopeBindings();

    // Biblioteca de archivos (enlace público o privado, se puede cambiar en cualquier momento)
    Route::get('library', [LibraryController::class, 'index'])->name('library.index');
    Route::post('library', [LibraryController::class, 'store'])->name('library.store');
    Route::put('library/{file}', [LibraryController::class, 'update'])->whereNumber('file')->name('library.update');
    Route::delete('library/{file}', [LibraryController::class, 'destroy'])->whereNumber('file')->name('library.destroy');
    Route::get('library/{file}/download', [LibraryController::class, 'download'])->whereNumber('file')->name('library.download');

    // Métricas de interacción por sede (Bogotá / Chía)
    Route::get('agents/{agent}/sede-metrics', [SedeMetricsController::class, 'index'])
        ->whereNumber('agent')->name('agents.sede-metrics.index');

    // Costos estimados de IA por agente
    Route::get('agents/{agent}/ai-costs', [AiCostController::class, 'index'])
        ->whereNumber('agent')->name('agents.ai-costs.index');

    // Base de conocimiento del agente (PDF/TXT/MD -> Markdown)
    Route::get('agents/{agent}/knowledge-base', [AgentKnowledgeBaseController::class, 'index'])
        ->whereNumber('agent')->name('agents.knowledge-base.index');
    Route::post('agents/{agent}/knowledge-base', [AgentKnowledgeBaseController::class, 'store'])
        ->whereNumber('agent')->name('agents.knowledge-base.store');
    Route::put('agents/{agent}/knowledge-base/{document}', [AgentKnowledgeBaseController::class, 'update'])
        ->whereNumber('agent')->name('agents.knowledge-base.update');
    Route::delete('agents/{agent}/knowledge-base/{document}', [AgentKnowledgeBaseController::class, 'destroy'])
        ->whereNumber('agent')->name('agents.knowledge-base.destroy');

    Route::put('agents/{agent}/call-config', [AgentConfigController::class, 'updateCallConfig'])->name('agents.call-config.update');
    Route::put('agents/{agent}/prompt-config', [AgentConfigController::class, 'updatePromptConfig'])->name('agents.prompt-config.update');
    Route::put('agents/{agent}/n8n-config', [AgentConfigController::class, 'updateN8nConfig'])->name('agents.n8n-config.update');

    Route::post('agents/{agent}/data-variables', [AgentConfigController::class, 'storeDataVariable'])->name('agents.data-variables.store');
    Route::delete('agents/{agent}/data-variables/{variable}', [AgentConfigController::class, 'destroyDataVariable'])->name('agents.data-variables.destroy');

    Route::post('agents/{agent}/extraction-variables', [AgentConfigController::class, 'storeExtractionVariable'])->name('agents.extraction-variables.store');
    Route::delete('agents/{agent}/extraction-variables/{variable}', [AgentConfigController::class, 'destroyExtractionVariable'])->name('agents.extraction-variables.destroy');

    Route::post('agents/{agent}/api-keys', [AgentConfigController::class, 'generateApiKey'])->name('agents.api-keys.store');
    Route::delete('agents/{agent}/api-keys/{apiKey}', [AgentConfigController::class, 'destroyApiKey'])->name('agents.api-keys.destroy');

    Route::post('agents/{agent}/client-fields', [AgentConfigController::class, 'storeClientField'])->name('agents.client-fields.store');
    Route::delete('agents/{agent}/client-fields/{clientField}', [AgentConfigController::class, 'destroyClientField'])->name('agents.client-fields.destroy')->scopeBindings();

    // Formulario de preguntas del agente (enlace público por cliente)
    Route::put('agents/{agent}/questions', [AgentFormController::class, 'updateQuestions'])->name('agents.questions.update');
    Route::get('agents/{agent}/form/responses', [AgentFormController::class, 'responses'])->name('agents.form.responses');
    Route::post('agents/{agent}/form/links', [AgentFormController::class, 'generateLinks'])->name('agents.form.links');
    Route::post('agents/{agent}/form/responses/{response}/regenerate', [AgentFormController::class, 'regenerate'])->name('agents.form.regenerate')->whereNumber('response');

    Route::get('agents/{agent}/reports', AgentReportController::class)->name('agents.reports.index');
    Route::post('agents/{agent}/reports/send', SendAgentReportController::class)->name('agents.reports.send');

    // Audio (base64) de un evento del log Post-Call de ElevenLabs, bajo demanda
    Route::get('agents/{agent}/elevenlabs/logs/{log}/audio', [PostCallLogController::class, 'audio'])
        ->whereNumber('agent')->whereNumber('log')
        ->name('agents.elevenlabs.logs.audio');

    // Reportes personalizados (constructor de widgets)
    Route::get('agents/{agent}/report-widgets/data', CustomReportController::class)->name('agents.report-widgets.data');
    Route::post('agents/{agent}/report-widgets/reorder', [AgentReportWidgetController::class, 'reorder'])->name('agents.report-widgets.reorder');
    Route::post('agents/{agent}/report-widgets', [AgentReportWidgetController::class, 'store'])->name('agents.report-widgets.store');
    Route::put('agents/{agent}/report-widgets/{reportWidget}', [AgentReportWidgetController::class, 'update'])->name('agents.report-widgets.update')->scopeBindings();
    Route::delete('agents/{agent}/report-widgets/{reportWidget}', [AgentReportWidgetController::class, 'destroy'])->name('agents.report-widgets.destroy')->scopeBindings();

    // Clientes
    Route::get('agents/{agent}/clients', [ClientController::class, 'index'])->name('agents.clients.index');
    Route::get('agents/{agent}/clients/for-campaign', [ClientController::class, 'listForCampaign'])->name('agents.clients.for-campaign');
    Route::post('agents/{agent}/clients/by-rules', [ClientController::class, 'clientsByRules'])->name('agents.clients.by-rules');
    Route::get('agents/{agent}/clients/{client}/detail', [ClientController::class, 'detail'])->name('agents.clients.detail')->scopeBindings();
    Route::post('agents/{agent}/clients/{client}/notes', [ClientController::class, 'storeNote'])->name('agents.clients.notes.store')->scopeBindings();
    Route::delete('agents/{agent}/clients/{client}/notes/{note}', [ClientController::class, 'destroyNote'])->name('agents.clients.notes.destroy')->scopeBindings();
    Route::get('agents/{agent}/clients/{client}/call-alerts', [ClientController::class, 'callAlerts'])->name('agents.clients.call-alerts.index');
    Route::patch('agents/{agent}/clients/{client}/call-alerts/{alerta}', [ClientController::class, 'updateCallAlert'])->name('agents.clients.call-alerts.update');
    Route::delete('agents/{agent}/clients/{client}/call-alerts/{alerta}', [ClientController::class, 'destroyCallAlert'])->name('agents.clients.call-alerts.destroy');
    Route::get('agents/{agent}/clients/{client}/whatsapp-messages', [ClientController::class, 'whatsappMessages'])->name('agents.clients.whatsapp-messages')->scopeBindings();
    Route::get('agents/{agent}/clients/{client}/call-transcript', [ClientController::class, 'callTranscript'])->name('agents.clients.call-transcript')->scopeBindings();
    Route::get('agents/{agent}/clients/{client}/call-audio', [ClientController::class, 'callAudio'])->name('agents.clients.call-audio')->scopeBindings();
    Route::post('agents/{agent}/clients/{client}/initiate-call', [ClientController::class, 'initiateCall'])->name('agents.clients.initiate-call')->scopeBindings();
    Route::post('agents/{agent}/clients/{client}/initiate-whatsapp', [ClientController::class, 'initiateWhatsapp'])->name('agents.clients.initiate-whatsapp')->scopeBindings();
    Route::post('agents/{agent}/clients/{client}/whatsapp-send', [ClientController::class, 'sendWhatsappMessage'])->name('agents.clients.whatsapp-send')->scopeBindings();
    Route::post('agents/{agent}/clients/{client}/whatsapp-media', [ClientController::class, 'sendWhatsappMedia'])->name('agents.clients.whatsapp-media')->scopeBindings();
    Route::patch('agents/{agent}/clients/{client}/ai-pause', [ClientController::class, 'setAiPause'])->name('agents.clients.ai-pause')->scopeBindings();
    Route::get('agents/{agent}/contact-queues', [ClientController::class, 'indexContactQueues'])->name('agents.contact-queues.index');
    Route::post('agents/{agent}/contact-queues', [ClientController::class, 'storeContactQueue'])->name('agents.contact-queues.store');
    Route::post('agents/{agent}/contact-queues/{contactQueue}/cancel', [ClientController::class, 'cancelContactQueue'])->name('agents.contact-queues.cancel')->scopeBindings();
    Route::get('agents/{agent}/callback-requests', [ClientCallbackRequestController::class, 'index'])->name('agents.callback-requests.index');
    Route::post('agents/{agent}/callback-requests', [ClientCallbackRequestController::class, 'store'])->name('agents.callback-requests.store');
    Route::post('agents/{agent}/callback-requests/{callbackRequest}/cancel', [ClientCallbackRequestController::class, 'cancel'])->name('agents.callback-requests.cancel')->scopeBindings();
    Route::post('agents/{agent}/clients', [ClientController::class, 'store'])->name('agents.clients.store');
    Route::post('agents/{agent}/clients/import', [ClientController::class, 'import'])->name('agents.clients.import');
    Route::get('agents/{agent}/clients/template', [ClientController::class, 'downloadTemplate'])->name('agents.clients.template');
    Route::get('agents/{agent}/clients/export', [ClientController::class, 'exportClients'])->name('agents.clients.export');
    Route::get('agents/{agent}/clients/export-collection', [ClientController::class, 'exportCollectionData'])->name('agents.clients.export-collection');
    Route::put('agents/{agent}/clients/{client}', [ClientController::class, 'update'])->name('agents.clients.update');
    Route::delete('agents/{agent}/clients/{client}', [ClientController::class, 'destroy'])->name('agents.clients.destroy');

    Route::get('/como-usar', [DocsController::class, 'index'])->name('docs.index');

    Route::middleware('role:administrador')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::delete('/settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.remove-logo');
        Route::delete('/settings/favicon', [SettingsController::class, 'removeFavicon'])->name('settings.remove-favicon');

        // Categorías de alerta de llamada (CRUD abierto)
        Route::post('/settings/alert-categories', [SettingsController::class, 'storeAlertCategory'])->name('settings.alert-categories.store');
        Route::put('/settings/alert-categories/{alertCategory}', [SettingsController::class, 'updateAlertCategory'])->name('settings.alert-categories.update');
        Route::delete('/settings/alert-categories/{alertCategory}', [SettingsController::class, 'destroyAlertCategory'])->name('settings.alert-categories.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/invite', [UserController::class, 'invite'])->name('users.invite');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Formulario público (sin autenticación). El token identifica al cliente.
Route::get('/f/{token}', [PublicFormController::class, 'show'])->name('public.form.show');
Route::post('/f/{token}', [PublicFormController::class, 'submit'])
    ->middleware('throttle:30,1')
    ->name('public.form.submit');

// Enlace público de un archivo de la biblioteca (solo si está marcado como público)
// Con extensión (/archivos/{token}.pdf) para que navegadores y apps reconozcan el tipo;
// sin extensión se mantiene para los enlaces que ya se compartieron.
Route::get('/archivos/{token}.{ext}', [LibraryController::class, 'publicShow'])
    ->where(['token' => '[A-Za-z0-9]{40}', 'ext' => '[A-Za-z0-9]{1,10}'])
    ->name('library.public');
Route::get('/archivos/{token}', [LibraryController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->name('library.public.legacy');

require __DIR__.'/auth.php';
