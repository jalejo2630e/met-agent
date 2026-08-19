<?php

namespace App\Mail;

use App\Models\Setting;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Reporte de operación de un agente enviado por correo (plantilla con marca).
 */
class OperationReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $report  Estructura de AgentOperationReportService::build()
     * @param  list<array{title: string, data: string, name: string}>  $charts  Gráficas del reporte (PNG binario) a incrustar inline
     */
    public function __construct(
        public string $agentName,
        public array $report,
        public array $charts = [],
    ) {}

    public function build(): static
    {
        $period = $this->report['period'] ?? [];
        $allTime = (bool) ($period['all_time'] ?? true);
        $periodLabel = $allTime
            ? 'Toda la operación'
            : trim(($period['date_from'] ?? '—').' a '.($period['date_to'] ?? '—'));

        $messages = (int) ($this->report['total_messages'] ?? 0);
        $messageCost = $this->numericSetting('message_cost');
        $estimatedCost = ($messageCost !== null && $messages > 0)
            ? number_format($messages * $messageCost, 2)
            : null;

        $metrics = [
            ['label' => 'Clientes', 'value' => number_format((int) ($this->report['total_clients'] ?? 0))],
            ['label' => 'Mensajes WhatsApp', 'value' => number_format($messages)],
            ['label' => 'Llamadas', 'value' => number_format((int) ($this->report['total_calls'] ?? 0))],
            ['label' => 'Clientes con datos recolectados', 'value' => number_format((int) ($this->report['total_with_collection'] ?? 0))],
        ];

        if ($estimatedCost !== null) {
            $metrics[] = ['label' => 'Costo estimado de mensajes (USD)', 'value' => '$'.$estimatedCost];
        }

        $branding = MailBranding::data();
        $logoUrl = $branding['logo_url'] ?? null;
        if (! is_string($logoUrl) || $logoUrl === '') {
            $logoUrl = asset('colsanitas.png');
        }

        return $this->subject('Reporte de operación — '.$this->agentName)
            ->view('emails.operation-report', [
                'agentName' => $this->agentName,
                'periodLabel' => $periodLabel,
                'metrics' => $metrics,
                'charts' => $this->charts,
                'mailBranding' => $branding,
                'logoUrl' => $logoUrl,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ]);
    }

    private function numericSetting(string $key): ?float
    {
        $v = Setting::get($key);

        return (is_numeric($v)) ? (float) $v : null;
    }
}
