<?php

namespace App\Services;

use App\Models\RegistroEscritoLlamada;
use App\Support\CallTranscriptsConnection;
use Illuminate\Support\Facades\Log;

/**
 * Conteo de llamadas por cliente/agente leyendo la tabla de registros de llamada
 * (Supabase REST o BD interna). La asociación llamada -> agente es por teléfono del
 * cliente, igual que el modal de transcripción; la campaña se toma de CAMPANA_AINOA.
 */
class CallCountService
{
    public function __construct(
        protected SupabaseCallTranscriptsRestService $rest
    ) {}

    /**
     * Mapa dígitos_de_teléfono => cantidad de llamadas.
     *
     * @param  iterable<string|null>  $phones
     * @return array<string, int>
     */
    public function countsByPhone(iterable $phones, ?string $from = null, ?string $to = null): array
    {
        $digits = $this->normalize($phones);
        if ($digits === []) {
            return [];
        }

        try {
            if (CallTranscriptsConnection::usesRest()) {
                return $this->rest->countsByPhone($digits, $from, $to);
            }

            return $this->internalCounts($digits, $from, $to);
        } catch (\Throwable $e) {
            Log::warning('[CallCountService] No se pudieron contar las llamadas: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Total de llamadas para un conjunto de teléfonos (reporte).
     *
     * @param  iterable<string|null>  $phones
     */
    public function totalForPhones(iterable $phones, ?string $from = null, ?string $to = null): int
    {
        $digits = $this->normalize($phones);
        if ($digits === []) {
            return 0;
        }

        try {
            if (CallTranscriptsConnection::usesRest()) {
                return $this->rest->countCallsForPhones($digits, $from, $to);
            }

            $q = RegistroEscritoLlamada::query()->forCampanaConfig()->whereIn('phone', $digits);
            if ($from !== null) {
                $q->where('created_at', '>=', $from);
            }
            if ($to !== null) {
                $q->where('created_at', '<=', $to);
            }

            return (int) $q->count();
        } catch (\Throwable $e) {
            Log::warning('[CallCountService] No se pudo contar el total de llamadas: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Asigna el atributo calls_count a cada cliente de la colección según su teléfono.
     *
     * @param  iterable<\App\Models\Client>  $clients
     */
    public function attachCallCounts(iterable $clients): void
    {
        $phones = [];
        foreach ($clients as $c) {
            $phones[] = $c->phone;
        }

        $map = $this->countsByPhone($phones);

        foreach ($clients as $c) {
            $digits = preg_replace('/\D/', '', (string) $c->phone);
            $c->calls_count = ($digits !== '' && isset($map[$digits])) ? $map[$digits] : 0;
        }
    }

    /**
     * @param  iterable<string|null>  $phones
     * @return list<string>
     */
    protected function normalize(iterable $phones): array
    {
        $digits = [];
        foreach ($phones as $p) {
            $d = preg_replace('/\D/', '', (string) $p);
            if ($d !== '') {
                $digits[$d] = true;
            }
        }

        return array_keys($digits);
    }

    /**
     * @param  list<string>  $digits
     * @return array<string, int>
     */
    protected function internalCounts(array $digits, ?string $from, ?string $to): array
    {
        $q = RegistroEscritoLlamada::query()->forCampanaConfig()->whereIn('phone', $digits);
        if ($from !== null) {
            $q->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $q->where('created_at', '<=', $to);
        }

        return $q->selectRaw('phone, count(*) as c')
            ->groupBy('phone')
            ->pluck('c', 'phone')
            ->map(fn ($c) => (int) $c)
            ->toArray();
    }
}
