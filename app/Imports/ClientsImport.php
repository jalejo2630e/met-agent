<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\ClientLoadDate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientsImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected \App\Models\Agent $agent,
        protected array $customFieldNames,
        protected array $map,
        protected \DateTime $loadedAt,
        public int $imported = 0,
        public array $errors = []
    ) {}

    public function collection(Collection $rows): void
    {
        $rowNum = 1;

        foreach ($rows as $row) {
            $rowNum++;
            $data = $row->toArray();

            // Mapear columnas (Excel usa headers como keys, puede variar formato)
            $mapped = [];
            foreach ($this->map as $header => $field) {
                foreach (array_keys($data) as $key) {
                    if (strtolower(str_replace([' ', '_', '-'], '', (string) $key)) === strtolower(str_replace([' ', '_', '-'], '', (string) $header))) {
                        $mapped[$field] = trim((string) ($data[$key] ?? ''));
                        break;
                    }
                }
            }
            foreach ($data as $key => $value) {
                $k = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $key));
                if ($k === 'nombre' && ! isset($mapped['name'])) {
                    $mapped['name'] = trim((string) $value);
                } elseif ($k === 'apellido' && ! isset($mapped['lastname'])) {
                    $mapped['lastname'] = trim((string) $value);
                } elseif ($k === 'email') {
                    $mapped['email'] = trim((string) $value);
                } elseif ($k === 'telefono' || $k === 'phone') {
                    $mapped['phone'] = trim((string) $value);
                } elseif ($k === 'tipodocumento' || $k === 'tipo_documento') {
                    $mapped['document_type'] = trim((string) $value);
                } elseif ($k === 'documento' || $k === 'document') {
                    $mapped['document'] = trim((string) $value);
                }
            }
            foreach ($this->customFieldNames as $fn) {
                $fnClean = strtolower(preg_replace('/[^a-z0-9]/', '', $fn));
                foreach ($data as $key => $value) {
                    if (strtolower(preg_replace('/[^a-z0-9]/', '', (string) $key)) === $fnClean) {
                        $mapped[$fn] = trim((string) $value);
                        break;
                    }
                }
                $mapped[$fn] = $mapped[$fn] ?? '';
            }

            $name = trim($mapped['name'] ?? '');
            $lastname = trim($mapped['lastname'] ?? '');
            $email = trim($mapped['email'] ?? '');

            if (! $name || ! $lastname || ! $email) {
                $this->errors[] = "Fila {$rowNum}: faltan nombre, apellido o email";
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Fila {$rowNum}: email inválido ({$email})";
                continue;
            }

            $customFields = [];
            foreach ($this->customFieldNames as $fn) {
                $customFields[$fn] = $mapped[$fn] ?? '';
            }

            $phoneRaw = $mapped['phone'] ?? null;
            $phoneNormalized = $phoneRaw !== null && $phoneRaw !== '' ? preg_replace('/\D/', '', (string) $phoneRaw) : null;

            $client = null;
            if ($phoneNormalized !== null && $phoneNormalized !== '') {
                $client = $this->agent->clients()->where('phone', $phoneNormalized)->first();
            }
            if (! $client) {
                $client = $this->agent->clients()->where('email', $email)->first();
            }

            if ($client) {
                $client->update([
                    'name' => $name,
                    'lastname' => $lastname,
                    'email' => $email,
                    'phone' => $phoneNormalized ?? $client->phone,
                    'document_type' => $mapped['document_type'] ?? $mapped['tipo_documento'] ?? $client->document_type,
                    'document' => $mapped['document'] ?? $mapped['documento'] ?? $client->document,
                    'custom_fields' => $customFields ?: $client->custom_fields,
                    'loaded_at' => $this->loadedAt,
                ]);
            } else {
                $client = $this->agent->clients()->create([
                    'name' => $name,
                    'lastname' => $lastname,
                    'email' => $email,
                    'phone' => $phoneNormalized,
                    'document_type' => $mapped['document_type'] ?? $mapped['tipo_documento'] ?? null,
                    'document' => $mapped['document'] ?? $mapped['documento'] ?? null,
                    'custom_fields' => $customFields ?: null,
                    'loaded_at' => $this->loadedAt,
                ]);
            }

            ClientLoadDate::create([
                'client_id' => $client->id,
                'agent_id' => $this->agent->id,
                'loaded_at' => $this->loadedAt,
                'source' => ClientLoadDate::SOURCE_IMPORT,
            ]);

            $this->imported++;
        }
    }
}
