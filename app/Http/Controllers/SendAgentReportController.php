<?php

namespace App\Http\Controllers;

use App\Mail\OperationReportMail;
use App\Models\Agent;
use App\Services\AgentOperationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SendAgentReportController extends Controller
{
    public function __invoke(Request $request, Agent $agent, AgentOperationReportService $reports): JsonResponse
    {
        $this->authorize('view', $agent);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'charts' => ['nullable', 'array', 'max:50'],
            'charts.*.title' => ['nullable', 'string', 'max:255'],
            'charts.*.image' => ['required_with:charts', 'string'],
        ]);

        $report = $reports->build($agent, $validated['date_from'] ?? null, $validated['date_to'] ?? null);

        $charts = $this->decodeCharts($validated['charts'] ?? []);

        Mail::to($validated['email'])->send(new OperationReportMail($agent->name, $report, $charts));

        return response()->json([
            'message' => 'Reporte enviado a '.$validated['email'].'.',
        ]);
    }

    /**
     * Decodifica las gráficas (dataURL PNG en base64) capturadas en el navegador
     * a datos binarios listos para incrustar en el correo.
     *
     * @param  array<int, array{title?: string|null, image?: string}>  $charts
     * @return list<array{title: string, data: string, name: string}>
     */
    private function decodeCharts(array $charts): array
    {
        $decoded = [];

        foreach (array_values($charts) as $i => $chart) {
            $image = (string) ($chart['image'] ?? '');
            // Quita el prefijo "data:image/png;base64," si viene incluido.
            if (str_contains($image, ',')) {
                $image = substr($image, strpos($image, ',') + 1);
            }
            $image = trim($image);
            if ($image === '') {
                continue;
            }

            $binary = base64_decode($image, true);
            // Descarta datos inválidos o imágenes vacías/desmesuradas (máx. ~4MB c/u).
            if ($binary === false || $binary === '' || strlen($binary) > 4 * 1024 * 1024) {
                continue;
            }

            $decoded[] = [
                'title' => trim((string) ($chart['title'] ?? '')),
                'data' => $binary,
                'name' => 'grafica-'.($i + 1).'.png',
            ];
        }

        return $decoded;
    }
}
