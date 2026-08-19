<?php

namespace App\Services;

use App\Models\User;
use App\Models\Setting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Lógica de autenticación en dos pasos con contraseñas de un solo uso basadas
 * en tiempo (TOTP), compatible con Google Authenticator y apps equivalentes.
 */
class TwoFactorAuthenticator
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * Genera una clave secreta nueva (base32) para asociar a un usuario.
     */
    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Verifica un código de 6 dígitos contra el secreto del usuario.
     * Se admite una ventana de ±1 intervalo (30 s) para compensar desfases de reloj.
     */
    public function verify(?string $secret, string $code): bool
    {
        if (empty($secret)) {
            return false;
        }

        return (bool) $this->engine->verifyKey($secret, preg_replace('/\s+/', '', $code), 1);
    }

    /**
     * Devuelve el QR (SVG en data URI) que el usuario escanea con su app.
     */
    public function qrCodeDataUri(User $user, string $secret): string
    {
        $issuer = $this->issuer();
        $otpauthUrl = $this->engine->getQRCodeUrl($issuer, $user->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(192, 0),
            new SvgImageBackEnd()
        );
        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Genera un lote de códigos de recuperación de un solo uso.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(int $amount = 8): array
    {
        return collect(range(1, $amount))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }

    /**
     * Nombre que aparece en la app de autenticación (nombre de la empresa
     * de la configuración; si no existe, el nombre de la aplicación).
     */
    private function issuer(): string
    {
        try {
            if (Schema::hasTable('settings')) {
                $companyName = Setting::get('company_name');
                if (is_string($companyName) && trim($companyName) !== '') {
                    return trim($companyName);
                }
            }
        } catch (\Throwable) {
            // Continúa con el valor por defecto.
        }

        return (string) config('app.name', 'Laravel');
    }
}
