<?php

namespace App\Console\Commands;

use App\Mail\SystemMarkdownMail;
use App\Models\Setting;
use App\Services\MessageQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Revisa el consumo de mensajes del mes vs el tope configurado y envía alertas
 * por correo al contacto de configuración cuando se cruza 50%, 80% o 100%.
 * Cada umbral se avisa una sola vez por mes.
 */
class CheckMessageQuota extends Command
{
    protected $signature = 'messages:check-quota';

    protected $description = 'Revisa el consumo de mensajes del mes vs el tope y alerta al correo de contacto (50%, 80%, 100%).';

    public function handle(MessageQuotaService $quota): int
    {
        $cap = (int) (Setting::get('monthly_message_cap') ?? 0);
        if ($cap <= 0) {
            $this->info('No hay tope de mensajes configurado. Nada que revisar.');

            return self::SUCCESS;
        }

        $used = $quota->currentMonthMessageCount();
        $pct = ($used / $cap) * 100;
        $monthKey = $quota->currentMonthKey();

        $this->info(sprintf('Mensajes del mes %s: %d / %d (%.1f%%).', $monthKey, $used, $cap, $pct));

        [$storedMonth, $sent] = $this->loadState();

        // Reinicio al cambiar de mes.
        if ($storedMonth !== $monthKey) {
            $sent = [];
        }

        $newlyCrossed = array_values(array_filter(
            MessageQuotaService::THRESHOLDS,
            fn (int $t) => $pct >= $t && ! in_array($t, $sent, true)
        ));

        if ($newlyCrossed === []) {
            // Persistir el reinicio de mes aunque no haya nada que enviar.
            if ($storedMonth !== $monthKey) {
                $this->saveState($monthKey, $sent);
            }

            return self::SUCCESS;
        }

        $recipient = trim((string) (Setting::get('contact_email') ?? ''));
        if ($recipient === '') {
            Log::warning('[messages:check-quota] Se cruzó el/los umbral(es) '.implode('%, ', $newlyCrossed).'% pero no hay contact_email configurado; no se envió alerta.');
            $this->warn('Se cruzó un umbral pero no hay correo de contacto configurado. No se marca como enviado.');

            return self::SUCCESS;
        }

        // Un solo correo por corrida: el umbral más alto recién cruzado.
        $threshold = max($newlyCrossed);
        $this->sendAlert($recipient, $threshold, $used, $cap, $pct, $monthKey);

        $sent = array_values(array_unique(array_merge($sent, $newlyCrossed)));
        sort($sent);
        $this->saveState($monthKey, $sent);

        $this->info('Alerta enviada a '.$recipient.' (umbral '.$threshold.'%).');

        return self::SUCCESS;
    }

    protected function sendAlert(string $recipient, int $threshold, int $used, int $cap, float $pct, string $monthKey): void
    {
        $companyName = trim((string) (Setting::get('company_name') ?? '')) ?: (string) config('app.name');

        $subject = sprintf('Alerta de consumo: %d%% del tope mensual de mensajes', $threshold);
        $heading = $threshold >= 100
            ? 'Alcanzaste el tope de mensajes del mes'
            : sprintf('Has superado el %d%% del tope de mensajes del mes', $threshold);

        $lines = [
            sprintf('Aviso automático de consumo de mensajes de WhatsApp para %s.', $companyName),
            sprintf('Mes: %s.', $monthKey),
            sprintf('Mensajes enviados este mes: %s.', number_format($used)),
            sprintf('Tope mensual configurado: %s.', number_format($cap)),
            sprintf('Consumo actual: %.1f%% del tope.', $pct),
        ];

        if ($threshold >= 100) {
            $lines[] = 'Has alcanzado o superado el 100% del tope. Revisa tu plan o ajusta el tope en la configuración.';
        }

        Mail::to($recipient)->send(new SystemMarkdownMail(
            $subject,
            $heading,
            $lines,
            route('settings.index'),
            'Ver configuración',
        ));
    }

    /**
     * Estado guardado: [mes 'Y-m'|null, umbrales ya enviados].
     *
     * @return array{0: string|null, 1: list<int>}
     */
    protected function loadState(): array
    {
        $raw = Setting::get('message_cap_alert_state');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [null, []];
        }

        $month = is_string($decoded['month'] ?? null) ? $decoded['month'] : null;
        $sent = array_values(array_filter(
            array_map('intval', (array) ($decoded['sent'] ?? [])),
            fn (int $v) => in_array($v, MessageQuotaService::THRESHOLDS, true)
        ));

        return [$month, $sent];
    }

    /**
     * @param  list<int>  $sent
     */
    protected function saveState(string $month, array $sent): void
    {
        Setting::set('message_cap_alert_state', json_encode([
            'month' => $month,
            'sent' => array_values($sent),
        ]));
    }
}
