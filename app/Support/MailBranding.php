<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class MailBranding
{
    /**
     * Datos para plantillas de correo (color principal, nombre, logo).
     * Seguro cuando no existe la tabla `settings` o en consola sin BD.
     *
     * @return array{
     *   company_name: string,
     *   display_name: string,
     *   primary_hex: string,
     *   button_foreground: string,
     *   link_hex: string,
     *   logo_url: string|null,
     *   app_name: string
     * }
     */
    public static function data(): array
    {
        $appName = (string) config('app.name', 'Laravel');
        $defaults = [
            'company_name' => $appName,
            'display_name' => $appName,
            'primary_hex' => '#a3e635',
            'button_foreground' => '#1a1a1a',
            'link_hex' => '#33475b',
            'logo_url' => asset('colsanitas.png'),
            'app_name' => $appName,
        ];

        try {
            if (! Schema::hasTable('settings')) {
                return $defaults;
            }

            $primary = Setting::get('primary_color') ?? '#a3e635';
            if (! is_string($primary) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $primary)) {
                $primary = '#a3e635';
            }

            $companyName = Setting::get('company_name');
            $displayName = (is_string($companyName) && trim($companyName) !== '')
                ? trim($companyName)
                : $appName;

            $logoPath = Setting::get('company_logo');
            $logoUrl = (is_string($logoPath) && $logoPath !== '')
                ? asset('storage/'.$logoPath)
                : asset('colsanitas.png');

            $fg = self::buttonForegroundForHex($primary);
            $link = self::linkColorForPrimary($primary);

            return [
                'company_name' => $displayName,
                'display_name' => $displayName,
                'primary_hex' => $primary,
                'button_foreground' => $fg,
                'link_hex' => $link,
                'logo_url' => $logoUrl,
                'app_name' => $appName,
            ];
        } catch (\Throwable) {
            return $defaults;
        }
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Contraste legible sobre el color principal (botones).
     */
    private static function buttonForegroundForHex(string $hex): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $luma = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luma > 0.55 ? '#1a1a1a' : '#ffffff';
    }

    /**
     * Color de enlaces en el cuerpo (legible sobre fondo claro).
     */
    private static function linkColorForPrimary(string $hex): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $mix = static fn (int $c, int $toward): int => (int) round($c * 0.35 + $toward * 0.65);

        return sprintf('#%02x%02x%02x', $mix($r, 51), $mix($g, 71), $mix($b, 91));
    }
}
