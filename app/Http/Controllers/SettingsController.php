<?php

namespace App\Http\Controllers;

use App\Models\AlertaCategoria;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\MessageQuotaService;
use App\Support\CallTranscriptsConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'company_name' => Setting::get('company_name'),
                'contact_name' => Setting::get('contact_name'),
                'contact_email' => Setting::get('contact_email'),
                'company_logo' => Setting::get('company_logo'),
                'site_favicon' => Setting::get('site_favicon'),
                'payment_method' => Setting::get('payment_method'),
                'message_cost' => Setting::get('message_cost'),
                'call_minute_cost' => Setting::get('call_minute_cost'),
                'monthly_message_cap' => Setting::get('monthly_message_cap'),
                'primary_color' => Setting::get('primary_color') ?? '#009B41',
                'whatsapp_conversations_source' => Setting::get('whatsapp_conversations_source') ?? config('services.whatsapp_conversations.source', 'internal'),
                'whatsapp_conversations_supabase_table_pattern' => Setting::get('whatsapp_conversations_supabase_table_pattern'),
                'whatsapp_conversations_supabase_phone_column' => Setting::get('whatsapp_conversations_supabase_phone_column'),
                'whatsapp_conversations_supabase_date_column' => Setting::get('whatsapp_conversations_supabase_date_column'),
                'call_transcripts_source' => Setting::get('call_transcripts_source') ?? CallTranscriptsConnection::source(),
                'call_alert_emails' => $this->callAlertEmails(),
            ],
            'alertCategories' => AlertaCategoria::orderBy('nombre')->get(['id', 'nombre', 'slug', 'color']),
            'messageQuota' => $this->messageQuota(),
            'transactions' => Transaction::orderBy('transacted_at', 'desc')->limit(50)->get(),
        ]);
    }

    /**
     * Consumo del mes actual vs el tope (para mostrar en la configuración).
     *
     * @return array{cap: int, used: int|null, percent: float|null, month: string}|null
     */
    private function messageQuota(): ?array
    {
        $cap = (int) (Setting::get('monthly_message_cap') ?? 0);
        if ($cap <= 0) {
            return null;
        }

        $service = app(MessageQuotaService::class);

        try {
            $used = $service->currentMonthMessageCount();

            return [
                'cap' => $cap,
                'used' => $used,
                'percent' => round(($used / $cap) * 100, 1),
                'month' => $service->currentMonthKey(),
            ];
        } catch (\Throwable) {
            return [
                'cap' => $cap,
                'used' => null,
                'percent' => null,
                'month' => $service->currentMonthKey(),
            ];
        }
    }

    /**
     * Correos configurados para notificar nuevas alertas de llamada.
     *
     * @return list<string>
     */
    private function callAlertEmails(): array
    {
        $decoded = json_decode((string) (Setting::get('call_alert_emails') ?? '[]'), true);

        return is_array($decoded)
            ? array_values(array_filter(array_map('trim', $decoded)))
            : [];
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'message_cost' => $request->input('message_cost') === '' ? null : $request->input('message_cost'),
            'call_minute_cost' => $request->input('call_minute_cost') === '' ? null : $request->input('call_minute_cost'),
            'monthly_message_cap' => $request->input('monthly_message_cap') === '' ? null : $request->input('monthly_message_cap'),
        ]);

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'company_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'site_favicon' => ['nullable', 'file', 'max:2048', 'mimes:jpeg,png,jpg,gif,webp,svg,ico'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'message_cost' => ['nullable', 'numeric', 'min:0'],
            'call_minute_cost' => ['nullable', 'numeric', 'min:0'],
            'monthly_message_cap' => ['nullable', 'integer', 'min:0'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'whatsapp_conversations_source' => ['required', 'string', 'in:internal,supabase'],
            'whatsapp_conversations_supabase_table_pattern' => [
                'nullable',
                'string',
                'max:128',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $t = trim((string) $value);
                    if (str_contains($t, '{agent_id}')) {
                        if (! preg_match('/^[a-zA-Z0-9_]*\{agent_id\}[a-zA-Z0-9_]*$/', $t)) {
                            $fail('Con {agent_id}, el patrón solo puede usar letras, números y guiones bajos alrededor del marcador.');
                        }

                        return;
                    }
                    if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $t)) {
                        $fail('Sin {agent_id}, indica el nombre fijo de la tabla (identificador PostgreSQL válido).');
                    }
                },
            ],
            'whatsapp_conversations_supabase_phone_column' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim((string) $value))) {
                        $fail('El nombre de columna solo puede tener letras, números y guiones bajos, y debe empezar por letra o _.');
                    }
                },
            ],
            'whatsapp_conversations_supabase_date_column' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim((string) $value))) {
                        $fail('El nombre de columna solo puede tener letras, números y guiones bajos, y debe empezar por letra o _.');
                    }
                },
            ],
            'call_transcripts_source' => ['required', 'string', 'in:internal,supabase'],
            'call_alert_emails' => ['nullable', 'array'],
            'call_alert_emails.*' => ['email', 'max:255'],
        ]);

        if (! empty($validated['company_name'])) {
            Setting::set('company_name', $validated['company_name']);
        } else {
            Setting::set('company_name', null);
        }

        if (array_key_exists('contact_name', $validated)) {
            Setting::set('contact_name', $validated['contact_name'] ?? null);
        }
        if (array_key_exists('contact_email', $validated)) {
            Setting::set('contact_email', $validated['contact_email'] ?? null);
        }

        if ($request->hasFile('company_logo')) {
            $oldLogo = Setting::get('company_logo');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            $path = $request->file('company_logo')->store('logos', 'public');
            Setting::set('company_logo', $path);
        }

        if ($request->hasFile('site_favicon')) {
            $oldFavicon = Setting::get('site_favicon');
            if ($oldFavicon && Storage::disk('public')->exists($oldFavicon)) {
                Storage::disk('public')->delete($oldFavicon);
            }

            $path = $request->file('site_favicon')->store('favicons', 'public');
            Setting::set('site_favicon', $path);
        }

        if (array_key_exists('payment_method', $validated)) {
            Setting::set('payment_method', $validated['payment_method']);
        }

        if (array_key_exists('message_cost', $validated)) {
            $v = $validated['message_cost'];
            Setting::set('message_cost', ($v !== null && $v !== '') ? (string) $v : null);
        }
        if (array_key_exists('call_minute_cost', $validated)) {
            $v = $validated['call_minute_cost'];
            Setting::set('call_minute_cost', ($v !== null && $v !== '') ? (string) $v : null);
        }

        if (array_key_exists('monthly_message_cap', $validated)) {
            $v = $validated['monthly_message_cap'];
            $newCap = ($v !== null && $v !== '') ? (string) (int) $v : null;
            if ($newCap !== Setting::get('monthly_message_cap')) {
                // El tope cambió: reevaluar umbrales este mes desde cero.
                Setting::set('message_cap_alert_state', null);
            }
            Setting::set('monthly_message_cap', $newCap);
        }

        if (array_key_exists('primary_color', $validated)) {
            Setting::set('primary_color', $validated['primary_color'] ?? '#009B41');
        }

        Setting::set('whatsapp_conversations_source', $validated['whatsapp_conversations_source']);
        $pattern = $validated['whatsapp_conversations_supabase_table_pattern'] ?? null;
        $patternTrim = is_string($pattern) ? trim($pattern) : '';
        Setting::set('whatsapp_conversations_supabase_table_pattern', $patternTrim !== '' ? $patternTrim : null);
        $phoneCol = $validated['whatsapp_conversations_supabase_phone_column'] ?? null;
        $phoneColTrim = is_string($phoneCol) ? trim($phoneCol) : '';
        Setting::set('whatsapp_conversations_supabase_phone_column', $phoneColTrim !== '' ? $phoneColTrim : null);
        $dateCol = $validated['whatsapp_conversations_supabase_date_column'] ?? null;
        $dateColTrim = is_string($dateCol) ? trim($dateCol) : '';
        Setting::set('whatsapp_conversations_supabase_date_column', $dateColTrim !== '' ? $dateColTrim : null);
        Setting::set('call_transcripts_source', $validated['call_transcripts_source']);

        $alertEmails = collect($validated['call_alert_emails'] ?? [])
            ->map(fn ($e) => trim((string) $e))
            ->filter()
            ->unique()
            ->values()
            ->all();
        Setting::set('call_alert_emails', json_encode($alertEmails));

        return redirect()->route('settings.index')->with('success', 'Configuración actualizada.');
    }

    /**
     * Crea una categoría de alerta de llamada (CRUD abierto en configuración).
     */
    public function storeAlertCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        AlertaCategoria::create([
            'nombre' => $validated['nombre'],
            'slug' => $this->uniqueCategorySlug($validated['nombre']),
            'color' => $validated['color'] ?? null,
        ]);

        return back()->with('success', 'Categoría creada.');
    }

    public function updateAlertCategory(Request $request, AlertaCategoria $alertCategory): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $alertCategory->update([
            'nombre' => $validated['nombre'],
            'slug' => $this->uniqueCategorySlug($validated['nombre'], $alertCategory->id),
            'color' => $validated['color'] ?? null,
        ]);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroyAlertCategory(AlertaCategoria $alertCategory): RedirectResponse
    {
        // Las alertas asociadas conservan su registro y quedan sin categoría (nullOnDelete).
        $alertCategory->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    /**
     * Genera un slug único para la categoría a partir del nombre.
     */
    private function uniqueCategorySlug(string $nombre, ?int $ignoreId = null): string
    {
        $base = Str::slug($nombre) ?: 'categoria';
        $slug = $base;
        $i = 2;

        while (AlertaCategoria::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function removeLogo(): RedirectResponse
    {
        $logo = Setting::get('company_logo');
        if ($logo && Storage::disk('public')->exists($logo)) {
            Storage::disk('public')->delete($logo);
        }
        Setting::set('company_logo', null);

        return redirect()->route('settings.index')->with('success', 'Logo eliminado.');
    }

    public function removeFavicon(): RedirectResponse
    {
        $favicon = Setting::get('site_favicon');
        if ($favicon && Storage::disk('public')->exists($favicon)) {
            Storage::disk('public')->delete($favicon);
        }
        Setting::set('site_favicon', null);

        return redirect()->route('settings.index')->with('success', 'Favicon eliminado.');
    }
}
