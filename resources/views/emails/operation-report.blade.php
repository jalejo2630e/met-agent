@php
    $mailBranding = $mailBranding ?? \App\Support\MailBranding::data();
    $primary = $mailBranding['primary_hex'] ?? '#009B41';
    $accent = $mailBranding['link_hex'] ?? '#33475b';
    $company = $mailBranding['display_name'] ?? config('app.name');
    $logoUrl = $logoUrl ?? null;
    $generatedAt = $generatedAt ?? null;
    $charts = $charts ?? [];

    $ink = '#0f172a';
    $muted = '#6b7480';
    $hairline = '#edf0f4';
    $cardBorder = '#e4e8ee';
    $pageBg = '#eceff3';
    $serif = "Georgia, 'Times New Roman', Times, serif";
    $sans = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>Reporte de operación</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:{{ $pageBg }}; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px; color:{{ $pageBg }};">Reporte de operación de {{ $agentName }} — {{ $periodLabel }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $pageBg }};">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border:1px solid {{ $cardBorder }}; border-radius:18px; overflow:hidden;">

                    <!-- Franja de marca -->
                    <tr>
                        <td style="height:6px; line-height:6px; font-size:6px; background-color:{{ $primary }};">&nbsp;</td>
                    </tr>

                    <!-- Encabezado: logo + etiqueta -->
                    <tr>
                        <td style="padding:26px 36px 20px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" valign="middle">
                                        @if ($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $company }}" height="40" style="display:block; height:40px; width:auto; border:0; outline:none; text-decoration:none;">
                                        @else
                                            <span style="font-family:{{ $serif }}; font-size:20px; font-weight:700; color:{{ $ink }};">{{ $company }}</span>
                                        @endif
                                    </td>
                                    <td align="right" valign="middle" style="font-family:{{ $sans }}; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:{{ $muted }};">
                                        Reporte de operación
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Hero: agente + período -->
                    <tr>
                        <td style="padding:8px 36px 4px 36px;">
                            <p style="margin:0 0 6px 0; font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:{{ $accent }};">Empresa</p>
                            <h1 style="margin:0 0 16px 0; font-family:{{ $serif }}; font-size:30px; line-height:36px; font-weight:700; color:{{ $ink }};">{{ $agentName }}</h1>

                            <!-- Badge de período -->
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background-color:#f6f8fa; border:1px solid {{ $cardBorder }}; border-radius:999px; padding:7px 14px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td valign="middle" style="padding-right:8px;">
                                                    <span style="display:inline-block; width:8px; height:8px; border-radius:8px; background-color:{{ $primary }};">&nbsp;</span>
                                                </td>
                                                <td valign="middle" style="font-family:{{ $sans }}; font-size:12px; font-weight:600; color:#334155;">
                                                    {{ $periodLabel }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Métricas -->
                    <tr>
                        <td style="padding:24px 36px 8px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid {{ $cardBorder }}; border-radius:14px;">
                                @foreach ($metrics as $i => $m)
                                    <tr>
                                        <td style="padding:16px 20px; {{ $i < count($metrics) - 1 ? 'border-bottom:1px solid '.$hairline.';' : '' }}">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="left" valign="middle" style="font-family:{{ $sans }}; font-size:11px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:{{ $muted }};">
                                                        {{ $m['label'] }}
                                                    </td>
                                                    <td align="right" valign="middle" style="font-family:{{ $serif }}; font-size:24px; line-height:26px; font-weight:700; color:{{ $ink }}; white-space:nowrap;">
                                                        {{ $m['value'] }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <!-- Gráficas del reporte -->
                    @if (!empty($charts))
                        <tr>
                            <td style="padding:24px 36px 4px 36px;">
                                <p style="margin:0; font-family:{{ $sans }}; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:{{ $accent }};">Gráficas del reporte</p>
                            </td>
                        </tr>
                        @foreach ($charts as $chart)
                            <tr>
                                <td style="padding:12px 36px 4px 36px;">
                                    @if (!empty($chart['title']))
                                        <p style="margin:0 0 8px 0; font-family:{{ $serif }}; font-size:16px; line-height:20px; font-weight:700; color:{{ $ink }};">{{ $chart['title'] }}</p>
                                    @endif
                                    <img src="{{ $message->embedData($chart['data'], $chart['name'], 'image/png') }}" alt="{{ $chart['title'] ?? 'Gráfica del reporte' }}" width="528" style="display:block; width:100%; max-width:528px; height:auto; border:1px solid {{ $cardBorder }}; border-radius:14px;">
                                </td>
                            </tr>
                        @endforeach
                    @endif

                    <!-- Nota -->
                    <tr>
                        <td style="padding:16px 36px 28px 36px;">
                            <p style="margin:0; font-family:{{ $sans }}; font-size:13px; line-height:20px; color:{{ $muted }};">
                                Resumen de la operación de la empresa en el período indicado. Los mensajes y llamadas se calculan a partir de los registros de la plataforma.
                            </p>
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="padding:20px 36px; background-color:#fafbfc; border-top:1px solid {{ $hairline }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" valign="middle" style="font-family:{{ $sans }}; font-size:12px; font-weight:600; color:#334155;">
                                        {{ $company }}
                                    </td>
                                    @if ($generatedAt)
                                        <td align="right" valign="middle" style="font-family:{{ $sans }}; font-size:11px; color:{{ $muted }};">
                                            Generado el {{ $generatedAt }}
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                <p style="margin:18px 0 0 0; font-family:{{ $sans }}; font-size:11px; color:#98a2b3;">
                    Correo automático · {{ $company }}
                </p>

            </td>
        </tr>
    </table>
</body>
</html>
