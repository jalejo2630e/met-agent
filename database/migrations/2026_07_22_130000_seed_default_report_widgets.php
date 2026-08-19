<?php

use App\Models\Agent;
use App\Support\DefaultReportWidgets;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Crea los reportes personalizados por defecto (avance del proceso, llamadas y
     * satisfacción) para los agentes que existían antes de esta funcionalidad.
     * Los agentes nuevos los reciben automáticamente vía AgentObserver.
     */
    public function up(): void
    {
        Agent::query()
            ->whereDoesntHave('reportWidgets')
            ->chunkById(100, function ($agents) {
                foreach ($agents as $agent) {
                    DefaultReportWidgets::seedFor($agent);
                }
            });
    }

    public function down(): void
    {
        // No se revierten los reportes por defecto (podrían haber sido editados por el usuario).
    }
};
