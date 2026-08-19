<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\CallTranscriptsConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = [
            'company_name' => null,
            'company_logo_url' => asset('colsanitas.png'),
            'favicon_url' => asset('colsanitas.png'),
            'primary_color' => '#a3e635',
        ];
        $whatsappSource = config('services.whatsapp_conversations.source', 'internal');
        if (Schema::hasTable('settings')) {
            $companyLogo = Setting::get('company_logo');
            $siteFavicon = Setting::get('site_favicon');
            $settings = [
                'company_name' => Setting::get('company_name'),
                'company_logo_url' => $companyLogo ? asset('storage/'.$companyLogo) : asset('colsanitas.png'),
                'favicon_url' => $siteFavicon ? asset('storage/'.$siteFavicon) : asset('colsanitas.png'),
                'primary_color' => Setting::get('primary_color') ?? '#a3e635',
            ];
            $whatsappSource = Setting::get('whatsapp_conversations_source') ?? $whatsappSource;
        }

        return [
            ...parent::share($request),
            'whatsapp_conversations_source' => $whatsappSource,
            'call_transcripts_source' => CallTranscriptsConnection::source(),
            'campana_ainoa' => config('services.campana_ainoa'),
            'auth' => [
                'user' => $request->user(),
                'canDeleteAgents' => $request->user()?->isAdmin() ?? false,
                'canAccessSettings' => $request->user()?->isAdmin() ?? false,
                'canAccessWebhooksAndTechnical' => $request->user()?->isAdmin() ?? false,
            ],
            'settings' => $settings,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'errors' => fn () => $request->session()->get('errors')?->getBag('default')?->getMessages(),
            ],
        ];
    }
}
