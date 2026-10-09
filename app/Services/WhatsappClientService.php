<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Client;
use App\Models\ClientLoadDate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Registra como cliente a quien escribe por WhatsApp/SMS si aún no existe en el
 * agente. Se usa desde el webhook de Twilio y desde el comando de relleno.
 */
class WhatsappClientService
{
    /**
     * Crea el cliente si el número no existe en el agente (compara por los
     * últimos 10 dígitos). Nombre y correo son provisionales: los campos son
     * obligatorios y se completan después.
     *
     * @return bool true si se creó un cliente nuevo
     */
    public function ensure(Agent $agent, string $from, string $profileName = ''): bool
    {
        $digits = preg_replace('/\D/', '', $from);
        if ($digits === '') {
            return false;
        }
        $last10 = substr($digits, -10);

        // Bloqueo por agente+número: evita duplicados si llegan dos mensajes a la vez.
        return Cache::lock('client-ensure:'.$agent->id.':'.$last10, 10)->block(5, function () use ($agent, $digits, $last10, $profileName) {
            $exists = Client::where('agent_id', $agent->id)
                ->where(function ($q) use ($digits, $last10) {
                    $q->where('phone', $digits)->orWhere('phone', 'like', '%'.$last10);
                })
                ->exists();
            if ($exists) {
                return false;
            }

            $client = Client::create([
                'agent_id' => $agent->id,
                'phone' => $digits,
                'name' => trim($profileName) !== '' ? Str::limit(trim($profileName), 100, '') : 'WhatsApp '.$digits,
                'lastname' => '',
                'email' => 'whatsapp-'.$digits.'@temp.local',
                'loaded_at' => now(),
            ]);

            ClientLoadDate::create([
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'loaded_at' => now(),
                'source' => ClientLoadDate::SOURCE_WHATSAPP,
            ]);

            return true;
        });
    }
}
