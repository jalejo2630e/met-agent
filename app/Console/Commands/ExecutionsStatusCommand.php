<?php

namespace App\Console\Commands;

use App\Models\ContactQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExecutionsStatusCommand extends Command
{
    protected $signature = 'executions:status';

    protected $description = 'Diagnóstico: colas, jobs pendientes y fallidos para validar el flujo';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=== Diagnóstico de ejecuciones ===');
        $this->newLine();

        $this->showQueues();
        $this->showJobs();
        $this->showFailedJobs();
        $this->showTips();

        return self::SUCCESS;
    }

    private function showQueues(): void
    {
        $now = now();
        $ready = ContactQueue::where('status', ContactQueue::STATUS_PENDING)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->count();
        $future = ContactQueue::where('status', ContactQueue::STATUS_PENDING)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '>', $now);
            })
            ->count();
        $processing = ContactQueue::where('status', ContactQueue::STATUS_PROCESSING)->count();

        $this->line('<fg=cyan>Colas de contacto:</>');
        $this->line("  - Listas para ejecutar (next_run_at <= ahora): {$ready}");
        $this->line("  - Pendientes (futuro o sin hora): {$future}");
        $this->line("  - En procesamiento: {$processing}");
        if ($ready > 0) {
            $this->line('  <fg=green>→ contact-queues:process debería despachar ' . $ready . ' job(s)</>');
        }
        if ($ready > 0 && $future === 0 && $processing === 0) {
            $this->line('  <fg=yellow>→ Si no se ejecutan: verifica que queue:work esté corriendo</>');
        }
        $this->newLine();
    }

    private function showJobs(): void
    {
        $count = 0;
        try {
            $count = DB::table('jobs')->count();
        } catch (\Throwable) {
            $this->line('<fg=red>No se pudo leer tabla jobs</>');
            $this->newLine();
            return;
        }

        $this->line('<fg=cyan>Jobs en cola (database):</>');
        $this->line("  - Pendientes: {$count}");
        if ($count > 0) {
            $this->line('  <fg=yellow>→ Hay jobs esperando. ¿Está queue:work corriendo?</>');
        } else {
            $this->line('  → Sin jobs pendientes (normal si no hay colas listas)');
        }
        $this->newLine();
    }

    private function showFailedJobs(): void
    {
        $count = 0;
        try {
            $count = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return;
        }

        $this->line('<fg=cyan>Jobs fallidos:</>');
        $this->line("  - Total: {$count}");
        if ($count > 0) {
            $this->line('  <fg=red>→ Revisa storage/logs/laravel.log y php artisan queue:failed</>');
        }
        $this->newLine();
    }

    private function showTips(): void
    {
        $this->line('<fg=gray>Para validar el webhook:</>');
        $this->line('  1. php artisan executions:list --queues  → ver colas pendientes');
        $this->line('  2. tail -f storage/logs/laravel.log      → ver logs en tiempo real');
        $this->line('  3. Busca "[ContactQueueService] Llamada webhook" cuando se ejecute');
        $this->line('  4. Si ves "Clientes filtrados por reglas" → las reglas excluyeron clientes');
        $this->line('  5. Si ves "Webhook falló" → revisa status y body en el log');
        $this->newLine();
    }
}
