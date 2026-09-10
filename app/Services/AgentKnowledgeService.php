<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Base de conocimiento del agente:
 * - Convierte un archivo cargado (PDF, TXT o Markdown) a Markdown/texto plano.
 *   La conversión de PDF es local (smalot/pdfparser), sin llamadas a IA.
 * - Construye el bloque de contexto que se inyecta en el prompt del agente
 *   conversacional a partir de los documentos habilitados.
 */
class AgentKnowledgeService
{
    /**
     * Convierte el archivo a Markdown/texto plano.
     */
    public function toMarkdown(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return $this->cleanup($this->pdfToText($file->getRealPath()));
        }

        // TXT / Markdown / otros de texto: se usa el contenido tal cual.
        $raw = (string) file_get_contents($file->getRealPath());

        return $this->cleanup($raw);
    }

    /**
     * Extrae el texto de un PDF de forma local.
     */
    private function pdfToText(string $path): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);

            return (string) $pdf->getText();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Normaliza el texto extraído: recorta espacios y colapsa líneas en blanco.
     */
    private function cleanup(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Colapsa espacios/tabs repetidos manteniendo saltos de línea.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        // Recorta cada línea.
        $lines = array_map(fn ($l) => rtrim($l), explode("\n", $text));
        $text = implode("\n", $lines);
        // Máximo dos saltos de línea seguidos.
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Bloque de contexto para inyectar en el system_prompt del agente,
     * a partir de los documentos habilitados. Cadena vacía si no hay nada.
     */
    public function promptContext(Agent $agent): string
    {
        $docs = $agent->relationLoaded('knowledgeDocuments')
            ? $agent->knowledgeDocuments->where('enabled', true)
            : $agent->knowledgeDocuments()->where('enabled', true)->orderBy('order')->orderBy('id')->get();

        $parts = [];
        foreach ($docs as $doc) {
            $content = trim((string) $doc->content);
            if ($content === '') {
                continue;
            }
            $title = trim((string) $doc->title) ?: 'Documento';
            $parts[] = "### {$title}\n{$content}";
        }

        if ($parts === []) {
            return '';
        }

        return "## Base de conocimiento\n"
            ."Usa la siguiente información de referencia para responder. Si la respuesta no está aquí ni en tu configuración, indícalo con honestidad.\n\n"
            .implode("\n\n---\n\n", $parts);
    }
}
