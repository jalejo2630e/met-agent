<?php

namespace App\Services;

/**
 * Registro escrito de llamadas / transcripciones desde Supabase REST (sin PDO).
 */
class SupabaseCallTranscriptsRestService
{
    public function __construct(
        protected SupabaseRestClient $client
    ) {}

    protected function table(): string
    {
        return config('services.registro_escrito_llamadas.table', 'registro_escrito_llamada');
    }

    protected function campanaColumn(): string
    {
        $col = config('services.registro_escrito_llamadas.campana_column');
        $col = is_string($col) ? trim($col) : '';

        return $col !== '' ? $col : 'campana';
    }

    /**
     * Listado para modal de transcripciones: teléfono + campaña.
     *
     * No filtramos por agent_id: en Supabase suele guardarse un id externo (p. ej. agent_xxx de ElevenLabs),
     * no el PK numérico de Laravel, así que eq.{agent->id} devolvía siempre filas vacías.
     * La autorización sigue en el controlador (cliente pertenece al agente) y el filtro por phone.
     *
     * @return list<array<string, mixed>>
     */
    public function listRowsForPhoneAndCampana(string $phoneDigits, ?string $campana = null): array
    {
        $q = [
            'select' => 'id,phone,conversation_id,created_at,duration_call_seg,transcript,variables_extraidas',
            'phone' => 'eq.'.$phoneDigits,
            'order' => 'created_at.desc',
            'limit' => 500,
        ];
        $campana = $this->resolvedCampana($campana);
        if ($campana !== '') {
            $q[$this->campanaColumn()] = $this->postgrestCampanaLike($campana);
        }

        return $this->client->select($this->table(), $q);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $callId, ?string $campana = null): ?array
    {
        $q = [
            'select' => '*',
            'id' => 'eq.'.$callId,
            'limit' => 1,
        ];
        $campana = $this->resolvedCampana($campana);
        if ($campana !== '') {
            $q[$this->campanaColumn()] = $this->postgrestCampanaLike($campana);
        }

        $rows = $this->client->select($this->table(), $q);

        return $rows[0] ?? null;
    }

    public function sumDurationSecondsForCampana(?string $campana = null): int
    {
        $campana = $this->resolvedCampana($campana);
        $offset = 0;
        $limit = 1000;
        $sum = 0;

        while (true) {
            $q = [
                'select' => 'duration_call_seg',
                'order' => 'id.asc',
                'limit' => $limit,
                'offset' => $offset,
            ];
            if ($campana !== '') {
                $q[$this->campanaColumn()] = $this->postgrestCampanaLike($campana);
            }

            $rows = $this->client->select($this->table(), $q);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $sum += (int) ($row['duration_call_seg'] ?? 0);
            }
            if (count($rows) < $limit) {
                break;
            }
            $offset += $limit;
        }

        return $sum;
    }

    /**
     * Prioridad: valor explícito (query) > CAMPANA_AINOA en config.
     */
    protected function resolvedCampana(?string $explicit): string
    {
        $v = $explicit !== null ? trim($explicit) : '';
        if ($v !== '') {
            return $v;
        }
        $fromConfig = config('services.campana_ainoa');

        return is_string($fromConfig) ? trim($fromConfig) : '';
    }

    /**
     * PostgREST: `*` en el patrón se traduce a `%` en SQL (equivalente a LIKE '%valor%').
     */
    protected function postgrestCampanaLike(string $campana): string
    {
        return 'like.*'.$campana.'*';
    }

    /**
     * Comprueba que exista un registro escrito con este teléfono, campaña y conversation_id (p. ej. antes de servir audio).
     */
    public function rowExistsForConversation(string $phoneDigits, ?string $campana, string $conversationId): bool
    {
        $conversationId = trim($conversationId);
        if ($conversationId === '') {
            return false;
        }

        $q = [
            'select' => 'id',
            'phone' => 'eq.'.$phoneDigits,
            'conversation_id' => 'eq.'.$conversationId,
            'limit' => 1,
        ];
        $campana = $this->resolvedCampana($campana);
        if ($campana !== '') {
            $q[$this->campanaColumn()] = $this->postgrestCampanaLike($campana);
        }

        return $this->client->select($this->table(), $q) !== [];
    }

    /**
     * Número de llamadas por teléfono (para la columna "Llamadas" del listado).
     * Devuelve un mapa dígitos_de_teléfono => cantidad.
     *
     * @param  list<string>  $phoneDigits
     * @return array<string, int>
     */
    public function countsByPhone(array $phoneDigits, ?string $from = null, ?string $to = null, ?string $campana = null): array
    {
        $phoneDigits = array_values(array_unique(array_filter($phoneDigits)));
        if ($phoneDigits === []) {
            return [];
        }
        $campana = $this->resolvedCampana($campana);
        $result = [];

        foreach (array_chunk($phoneDigits, 100) as $chunk) {
            $q = [
                'select' => 'phone',
                'phone' => 'in.('.implode(',', $chunk).')',
                'limit' => 100000,
            ];
            if ($campana !== '') {
                $q[$this->campanaColumn()] = $this->postgrestCampanaLike($campana);
            }
            $this->applyDateRange($q, $from, $to);

            foreach ($this->client->select($this->table(), $q) as $row) {
                $p = preg_replace('/\D/', '', (string) ($row['phone'] ?? ''));
                if ($p === '') {
                    continue;
                }
                $result[$p] = ($result[$p] ?? 0) + 1;
            }
        }

        return $result;
    }

    /**
     * Total de llamadas para un conjunto de teléfonos (para el reporte).
     * Usa count=exact (sin transferir filas).
     *
     * @param  list<string>  $phoneDigits
     */
    public function countCallsForPhones(array $phoneDigits, ?string $from = null, ?string $to = null, ?string $campana = null): int
    {
        $phoneDigits = array_values(array_unique(array_filter($phoneDigits)));
        if ($phoneDigits === []) {
            return 0;
        }
        $campana = $this->resolvedCampana($campana);
        $total = 0;

        foreach (array_chunk($phoneDigits, 100) as $chunk) {
            $q = ['phone' => 'in.('.implode(',', $chunk).')'];
            if ($campana !== '') {
                $q[$this->campanaColumn()] = $this->postgrestCampanaLike($campana);
            }
            $this->applyDateRange($q, $from, $to);

            $total += $this->client->count($this->table(), $q);
        }

        return $total;
    }

    /**
     * Aplica un rango sobre created_at al query PostgREST.
     * Dos condiciones sobre la misma columna requieren el operador `and=(...)`.
     *
     * @param  array<string, string|int>  $q
     */
    protected function applyDateRange(array &$q, ?string $from, ?string $to): void
    {
        if ($from !== null && $to !== null) {
            $q['and'] = '(created_at.gte.'.$from.',created_at.lte.'.$to.')';
        } elseif ($from !== null) {
            $q['created_at'] = 'gte.'.$from;
        } elseif ($to !== null) {
            $q['created_at'] = 'lte.'.$to;
        }
    }
}
