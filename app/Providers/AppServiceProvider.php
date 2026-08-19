<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\Client;
use App\Observers\AgentObserver;
use App\Observers\ClientObserver;
use App\Support\MailBranding;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Agent::observe(AgentObserver::class);
        Client::observe(ClientObserver::class);
        Vite::prefetch(concurrency: 3);

        // Forzar HTTPS en producción para evitar Mixed Content (assets con http en página https)
        if (! $this->app->runningInConsole() && config('app.env') === 'production') {
            URL::forceScheme('https');
            $appUrl = config('app.url');
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceRootUrl($appUrl);
            }
        }

        // Nombre del remitente alineado con la marca (configuración), manteniendo la dirección de MAIL_FROM_ADDRESS.
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            try {
                $name = MailBranding::data()['company_name'] ?? null;
                if (! $name) {
                    return;
                }
                $from = $event->message->getFrom();
                if (count($from) === 0) {
                    return;
                }
                $first = $from[0];
                $event->message->from($first->getAddress(), $name);
            } catch (\Throwable) {
                //
            }
        });
    }
}
