<?php

use App\Models\AgentReportWidget;
use App\Support\DefaultReportWidgets;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Convierte los widgets de "Avance del proceso" que se sembraron con la lógica
     * antigua (tramos por STEP) a la nueva métrica topic_progress, que determina el
     * tema con los campos compañeros (fatiga/emociones) en lugar del STEP.
     *
     * Solo toca los que coinciden con la firma del reporte por defecto, para no
     * pisar reportes personalizados que el usuario haya creado a mano.
     */
    public function up(): void
    {
        AgentReportWidget::query()
            ->where('metric', AgentReportWidget::METRIC_RANGE_BUCKETS)
            ->where('title', 'Avance del proceso')
            ->get()
            ->each(function (AgentReportWidget $widget) {
                $widget->update([
                    'metric' => AgentReportWidget::METRIC_TOPIC_PROGRESS,
                    'field_name' => null,
                    'chart_type' => 'progress',
                    'config' => ['topics' => DefaultReportWidgets::defaultTopics(), 'width' => 'full'],
                ]);
            });
    }

    public function down(): void
    {
        // No se revierte (la lógica antigua era incorrecta).
    }
};
