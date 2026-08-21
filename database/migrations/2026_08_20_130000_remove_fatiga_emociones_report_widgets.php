<?php

use App\Models\AgentReportWidget;
use Illuminate\Database\Migrations\Migration;

/**
 * Elimina el widget de reporte por defecto "Avance del proceso" (Fatiga/Emociones)
 * de los agentes existentes; ya no aplica. Solo borra el widget por defecto o los
 * topic_progress cuyos temas mencionan fatiga/emociones, respetando los reportes
 * personalizados que el usuario haya creado con otros temas.
 */
return new class extends Migration
{
    public function up(): void
    {
        AgentReportWidget::query()
            ->where('metric', AgentReportWidget::METRIC_TOPIC_PROGRESS)
            ->get()
            ->each(function (AgentReportWidget $widget) {
                $topics = is_array($widget->config['topics'] ?? null) ? $widget->config['topics'] : [];

                $mencionaFatigaEmociones = collect($topics)->contains(function ($t) {
                    $texto = strtolower((string) (($t['field'] ?? '').' '.($t['label'] ?? '')));

                    return str_contains($texto, 'fatiga') || str_contains($texto, 'emocion');
                });

                if ($widget->title === 'Avance del proceso' || $mencionaFatigaEmociones) {
                    $widget->delete();
                }
            });
    }

    public function down(): void
    {
        // No se revierte.
    }
};
