<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\TwilioMessage;
use App\Services\WhatsappClientService;
use Illuminate\Console\Command;

/**
 * Crea como cliente a quienes ya escribieron (twilio_messages) y aún no existen
 * en el agente. Seguro de repetir: no duplica ni modifica clientes existentes.
 */
class BackfillClientsFromMessages extends Command
{
    protected $signature = 'clients:backfill-from-messages
        {--agent= : ID del agente (por defecto, todos)}
        {--dry-run : Solo muestra cuántos se crearían, sin guardar}';

    protected $description = 'Crea como clientes los números que escribieron por WhatsApp/SMS y no existen aún';

    public function handle(WhatsappClientService $clients): int
    {
        $agents = Agent::query()
            ->when($this->option('agent'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($agents->isEmpty()) {
            $this->error('No se encontró ningún agente.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($agents as $agent) {
            $created = 0;
            $numbers = TwilioMessage::where('agent_id', $agent->id)
                ->where('direction', 'inbound')
                ->selectRaw('from_number, max(id) as last_id')
                ->groupBy('from_number')
                ->pluck('last_id', 'from_number');

            $seen = [];
            foreach ($numbers as $from => $lastId) {
                // Un mismo número puede venir en varios formatos (+57…, 57…, 10 dígitos).
                $key = substr((string) preg_replace('/\D/', '', (string) $from), -10);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                // Nombre de perfil más reciente de ese número, si lo hay.
                $profile = (string) TwilioMessage::where('agent_id', $agent->id)
                    ->where('from_number', $from)
                    ->whereNotNull('profile_name')->where('profile_name', '!=', '')
                    ->orderByDesc('id')->value('profile_name');

                if ($dry) {
                    if (! $this->exists($agent, (string) $from)) {
                        $created++;
                    }
                } elseif ($clients->ensure($agent, (string) $from, $profile)) {
                    $created++;
                }
            }

            $this->line(sprintf('Agente #%d "%s": %d de %d números %s.', $agent->id, $agent->name, $created, count($seen), $dry ? 'se crearían' : 'creados'));
            $total += $created;
        }

        $this->info(($dry ? 'Total que se crearían: ' : 'Total creados: ').$total);

        return self::SUCCESS;
    }

    private function exists(Agent $agent, string $from): bool
    {
        $digits = preg_replace('/\D/', '', $from);
        if ($digits === '') {
            return true;
        }

        return \App\Models\Client::where('agent_id', $agent->id)
            ->where(fn ($q) => $q->where('phone', $digits)->orWhere('phone', 'like', '%'.substr($digits, -10)))
            ->exists();
    }
}
