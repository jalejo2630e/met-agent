<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lectura vía PostgREST (URL + anon key). No usa PostgreSQL directo.
 */
class SupabaseRestClient
{
    public function assertConfigured(): void
    {
        $url = (string) config('services.supabase.url');
        $key = (string) config('services.supabase.anon_key');
        if ($url === '' || $key === '') {
            throw new RuntimeException(
                'Supabase REST: define VITE_SUPABASE_URL y VITE_SUPABASE_ANON_KEY o VITE_SUPABASE_KEY (o SUPABASE_URL y SUPABASE_ANON_KEY) en .env.'
            );
        }
    }

    /**
     * @param  array<string, string|int>  $query
     * @return list<array<string, mixed>>
     */
    public function select(string $table, array $query = []): array
    {
        $this->assertConfigured();

        $url = $this->restTableUrl($table);
        $response = Http::timeout(60)
            ->withHeaders($this->headers())
            ->get($url, $query);

        if ($response->status() === 404) {
            return [];
        }

        $response->throw();

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Total de filas con los mismos filtros (Prefer: count=exact).
     */
    public function count(string $table, array $query = []): int
    {
        $this->assertConfigured();

        $query = array_merge($query, [
            'select' => 'id',
        ]);

        $url = $this->restTableUrl($table);
        $response = Http::timeout(60)
            ->withHeaders(array_merge($this->headers(), [
                'Prefer' => 'count=exact',
                'Range' => '0-0',
            ]))
            ->get($url, $query);

        if ($response->status() === 404) {
            return 0;
        }

        $response->throw();

        $range = $response->header('Content-Range');
        if (is_string($range) && preg_match('#/(\d+)$#', $range, $m)) {
            return (int) $m[1];
        }

        $json = $response->json();

        return is_array($json) ? count($json) : 0;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $key = (string) config('services.supabase.anon_key');

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
            'Accept' => 'application/json',
            'Accept-Profile' => 'public',
        ];
    }

    protected function restTableUrl(string $table): string
    {
        $base = rtrim((string) config('services.supabase.url'), '/');

        return $base.'/rest/v1/'.rawurlencode($table);
    }
}
