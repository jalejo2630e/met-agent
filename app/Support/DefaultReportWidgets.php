<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\AgentReportWidget;

/**
 * Definiciones de los reportes personalizados que vienen pre-creados por defecto
 * en cada agente (avance del proceso por STEP, llamadas y satisfacción). Es la
 * única fuente de verdad: la usan el observer (agentes nuevos) y la migración de
 * backfill (agentes existentes).
 */
class DefaultReportWidgets
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'title' => 'Avance del proceso',
                'metric' => AgentReportWidget::METRIC_TOPIC_PROGRESS,
                'source' => AgentReportWidget::SOURCE_CUSTOM_FIELD,
                'field_name' => null,
                'chart_type' => 'progress',
                'config' => [
                    'topics' => self::defaultTopics(),
                    'width' => 'full',
                ],
            ],
            [
                'title' => 'Llamadas realizadas',
                'metric' => AgentReportWidget::METRIC_CALLS,
                'source' => AgentReportWidget::SOURCE_CALLS,
                'field_name' => null,
                'chart_type' => 'stat',
                'config' => [
                    'calls_metrics' => ['total', 'avg', 'distribution'],
                ],
            ],
            [
                'title' => 'Calificación de satisfacción',
                'metric' => AgentReportWidget::METRIC_VALUE_COUNTS,
                'source' => AgentReportWidget::SOURCE_CUSTOM_FIELD,
                'field_name' => 'calificacion_satisfaccion',
                'chart_type' => 'bar',
                'config' => [
                    'values' => [
                        ['value' => '1', 'label' => '1 · Muy malo', 'color' => '#c62828'],
                        ['value' => '2', 'label' => '2 · Malo', 'color' => '#ef6c00'],
                        ['value' => '3', 'label' => '3 · Regular', 'color' => '#f9a825'],
                        ['value' => '4', 'label' => '4 · Bueno', 'color' => '#43a047'],
                        ['value' => '5', 'label' => '5 · Excelente', 'color' => '#2e7d32'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Temas por defecto del avance del proceso (campos compañeros 0..3).
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultTopics(): array
    {
        return [
            ['field' => 'fatiga', 'label' => 'Fatiga', 'max' => 3, 'color' => '#2e7d32'],
            ['field' => 'emociones', 'label' => 'Emociones', 'max' => 3, 'color' => '#1976d2'],
        ];
    }

    /**
     * Crea los reportes por defecto para un agente si aún no tiene ninguno.
     * No recrea nada si el agente ya tiene widgets (respeta lo que el usuario borre).
     */
    public static function seedFor(Agent $agent): void
    {
        if ($agent->reportWidgets()->exists()) {
            return;
        }

        foreach (self::definitions() as $order => $definition) {
            $agent->reportWidgets()->create([
                ...$definition,
                'order' => $order,
            ]);
        }
    }
}
