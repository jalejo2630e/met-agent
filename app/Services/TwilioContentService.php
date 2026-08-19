<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Crea y lista plantillas de WhatsApp en Twilio usando la Content API,
 * aprovechando las credenciales de Twilio ya configuradas (SID + Auth Token).
 *
 * Docs: https://www.twilio.com/docs/content/content-api-resources
 */
class TwilioContentService
{
    private const BASE = 'https://content.twilio.com/v1';

    public function isConfigured(): bool
    {
        return $this->sid() !== '' && $this->token() !== '';
    }

    private function sid(): string
    {
        return (string) config('services.twilio.account_sid', '');
    }

    private function token(): string
    {
        return (string) config('services.twilio.auth_token', '');
    }

    /**
     * Lista las plantillas (Content) con su estado de aprobación de WhatsApp.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $response = Http::withBasicAuth($this->sid(), $this->token())
            ->timeout(20)
            ->get(self::BASE.'/ContentAndApprovals', ['PageSize' => 50]);

        if (! $response->successful()) {
            return [];
        }

        $out = [];
        foreach (($response->json('contents') ?? []) as $content) {
            $approvals = $content['approval_requests'] ?? [];
            $whatsapp = $approvals['whatsapp'] ?? $approvals;

            $out[] = [
                'sid' => $content['sid'] ?? null,
                'friendly_name' => $content['friendly_name'] ?? null,
                'language' => $content['language'] ?? null,
                'status' => $whatsapp['status'] ?? ($approvals['status'] ?? 'unsubmitted'),
                'category' => $whatsapp['category'] ?? null,
                'date_created' => $content['date_created'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Crea una plantilla de texto en Twilio y (opcional) la envía a aprobación de WhatsApp.
     *
     * @param  array{friendly_name:string, language?:string, body:string, variables?:array<string,string>, category?:string, submit_whatsapp?:bool, whatsapp_name?:string}  $data
     * @return array<string, mixed>
     */
    public function createTemplate(array $data): array
    {
        $body = trim((string) ($data['body'] ?? ''));

        $variables = [];
        foreach (($data['variables'] ?? []) as $key => $value) {
            $variables[(string) $key] = (string) $value;
        }

        $payload = [
            'friendly_name' => $data['friendly_name'],
            'language' => $data['language'] ?? 'es',
            'types' => [
                'twilio/text' => ['body' => $body],
            ],
        ];
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $response = Http::withBasicAuth($this->sid(), $this->token())
            ->asJson()
            ->timeout(30)
            ->post(self::BASE.'/Content', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Twilio Content API: '.$response->status().' '.$response->body());
        }

        $content = $response->json();
        $sid = $content['sid'] ?? null;

        $approval = null;
        if (! empty($data['submit_whatsapp']) && $sid) {
            $approval = $this->submitWhatsappApproval(
                $sid,
                $data['whatsapp_name'] ?? $data['friendly_name'],
                $data['category'] ?? 'UTILITY',
            );
        }

        return ['sid' => $sid, 'content' => $content, 'approval' => $approval];
    }

    /**
     * Envía una plantilla ya creada a aprobación de WhatsApp.
     *
     * @return array<string, mixed>
     */
    public function submitWhatsappApproval(string $contentSid, string $name, string $category): array
    {
        $response = Http::withBasicAuth($this->sid(), $this->token())
            ->asJson()
            ->timeout(30)
            ->post(self::BASE."/Content/{$contentSid}/ApprovalRequests/whatsapp", [
                'name' => $this->normalizeName($name),
                'category' => strtoupper($category),
            ]);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Nombre válido para plantilla de WhatsApp: minúsculas, alfanumérico y guion bajo.
     */
    private function normalizeName(string $name): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($name));

        return trim((string) $normalized, '_') ?: 'template';
    }
}
