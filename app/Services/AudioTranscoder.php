<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Normaliza audio de salida de WhatsApp a MP3 mono con ffmpeg.
 *
 * Los navegadores (Chrome/Edge) graban en WebM/Opus, que WhatsApp (Twilio/Meta)
 * rechaza con el error 63021. MP3 mono es aceptado sin problemas, así que toda
 * nota de voz se transcodifica antes de enviarse.
 */
class AudioTranscoder
{
    public function isAvailable(): bool
    {
        try {
            return Process::timeout(15)->run(['ffmpeg', '-version'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Transcodifica el archivo de audio a MP3 mono y lo guarda en el disco public.
     *
     * @return array{0: string, 1: string} [ruta relativa en el disco public, mime]
     *
     * @throws \RuntimeException si ffmpeg no está disponible o falla.
     */
    public function toMp3(string $inputPath): array
    {
        $relative = 'whatsapp-media/'.Str::uuid()->toString().'.mp3';
        $output = Storage::disk('public')->path($relative);

        $dir = dirname($output);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            $result = Process::timeout(120)->run([
                'ffmpeg', '-y',
                '-i', $inputPath,
                '-vn',                 // descarta cualquier pista de video (contenedor webm)
                '-ac', '1',            // mono
                '-ar', '44100',        // 44.1 kHz
                '-c:a', 'libmp3lame',
                '-b:a', '64k',         // liviano para voz
                '-map_metadata', '-1', // limpia metadatos
                $output,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('ffmpeg no está disponible: '.$e->getMessage());
        }

        if (! $result->successful() || ! is_file($output)) {
            throw new \RuntimeException('ffmpeg no pudo convertir el audio: '.$result->errorOutput());
        }

        return [$relative, 'audio/mpeg'];
    }
}
