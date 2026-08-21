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

            $body = $this->extractBody($content['types'] ?? null);

            $out[] = [
                'sid' => $content['sid'] ?? null,
                'friendly_name' => $content['friendly_name'] ?? null,
                'language' => $content['language'] ?? null,
                'status' => $whatsapp['status'] ?? ($approvals['status'] ?? 'unsubmitted'),
                'category' => $whatsapp['category'] ?? null,
                'date_created' => $content['date_created'] ?? null,
                'body' => $body,
                'variables' => $this->extractVariables($body, $content['variables'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * Extrae el cuerpo de texto de una plantilla desde su bloque `types`
     * (twilio/text, twilio/quick-reply, etc.).
     *
     * @param  mixed  $types
     */
    private function extractBody($types): string
    {
        if (! is_array($types)) {
            return '';
        }

        foreach ($types as $type) {
            if (is_array($type) && isset($type['body']) && $type['body'] !== '') {
                return (string) $type['body'];
            }
        }

        return '';
    }

    /**
     * Variables de la plantilla: placeholders {{...}} del cuerpo, o las claves del
     * mapa `variables` que devuelve Twilio. Devuelve una lista ordenada (numérica
     * si son números, ej. ["1","2"]).
     *
     * @param  mixed  $variablesMap
     * @return list<string>
     */
    private function extractVariables(string $body, $variablesMap): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $body, $matches);
        $vars = array_values(array_unique($matches[1] ?? []));

        if ($vars === [] && is_array($variablesMap)) {
            $vars = array_map('strval', array_keys($variablesMap));
        }

        usort($vars, fn ($a, $b) => (is_numeric($a) && is_numeric($b)) ? ((int) $a <=> (int) $b) : strcmp((string) $a, (string) $b));

        return $vars;
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
     * ¿Se puede enviar WhatsApp por Twilio? (credenciales + remitente definidos).
     */
    public function canSendWhatsapp(): bool
    {
        return $this->isConfigured() && $this->whatsappFrom() !== '';
    }

    private function whatsappFrom(): string
    {
        return (string) config('services.twilio.whatsapp_from', '');
    }

    /**
     * Envía una plantilla de WhatsApp directamente por la API de mensajes de
     * Twilio (sin webhook externo). Usa el Content SID de la plantilla y sus
     * variables como ContentVariables ({"1":"valor", ...}).
     *
     * @param  array<string|int, string>  $variables  mapa posición/clave => valor
     * @return array<string, mixed>
     */
    public function sendWhatsappTemplate(string $toPhone, string $contentSid, array $variables = []): array
    {
        if (! $this->canSendWhatsapp()) {
            throw new \RuntimeException('Twilio no está configurado para enviar WhatsApp (define TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN y TWILIO_WHATSAPP_FROM).');
        }

        $payload = [
            'From' => $this->whatsappFrom(),
            'To' => $this->toWhatsappAddress($toPhone),
            'ContentSid' => $contentSid,
        ];
        if ($variables !== []) {
            // Twilio espera un objeto JSON {"1":"valor"}; forzar objeto aunque las claves sean numéricas.
            $payload['ContentVariables'] = json_encode((object) $variables, JSON_UNESCAPED_UNICODE);
        }

        $response = Http::withBasicAuth($this->sid(), $this->token())
            ->asForm()
            ->timeout(30)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$this->sid().'/Messages.json', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Twilio Messages API: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Envía un mensaje de texto libre de WhatsApp (mensaje de sesión, solo válido
     * dentro de la ventana de 24h; fuera de ella Twilio lo rechaza y hay que usar
     * una plantilla). Devuelve la respuesta de Twilio.
     *
     * @return array<string, mixed>
     */
    public function sendWhatsappText(string $toPhone, string $body): array
    {
        if (! $this->canSendWhatsapp()) {
            throw new \RuntimeException('Twilio no está configurado para enviar WhatsApp (define TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN y TWILIO_WHATSAPP_FROM).');
        }

        $response = Http::withBasicAuth($this->sid(), $this->token())
            ->asForm()
            ->timeout(30)
            ->post('https://api.twilio.com/2010-04-01/Accounts/'.$this->sid().'/Messages.json', [
                'From' => $this->whatsappFrom(),
                'To' => $this->toWhatsappAddress($toPhone),
                'Body' => $body,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Twilio Messages API: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Normaliza un teléfono a dirección de WhatsApp de Twilio (whatsapp:+E164).
     * Si el número no trae código de país y parece nacional, antepone el código
     * por defecto (TWILIO_DEFAULT_COUNTRY_CODE, 57 = Colombia).
     */
    private function toWhatsappAddress(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $cc = preg_replace('/\D/', '', (string) config('services.twilio.default_country_code', '57')) ?? '';

        if ($cc !== '' && ! str_starts_with($digits, $cc) && strlen($digits) <= 10) {
            $digits = $cc.$digits;
        }

        return 'whatsapp:+'.$digits;
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
