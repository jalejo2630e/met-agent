<?php

namespace App\Services;

/**
 * Audio de llamadas en Supabase (tabla registro_audio_llamadas, columna audio base64).
 */
class SupabaseCallAudioRestService
{
    public function __construct(
        protected SupabaseRestClient $client
    ) {}

    protected function table(): string
    {
        return config('services.registro_audio_llamadas.table', 'registro_audio_llamadas');
    }

    public function findAudioBase64(string $conversationId): ?string
    {
        $conversationId = trim($conversationId);
        if ($conversationId === '') {
            return null;
        }

        $rows = $this->client->select($this->table(), [
            'select' => 'audio',
            'conversation_id' => 'eq.'.$conversationId,
            'limit' => 1,
        ]);

        if ($rows === []) {
            return null;
        }

        $audio = $rows[0]['audio'] ?? null;

        return is_string($audio) && $audio !== '' ? $audio : null;
    }

    /**
     * @param  list<string|null>  $conversationIds
     * @return list<string>
     */
    public function filterConversationIdsWithAudio(array $conversationIds): array
    {
        $conversationIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => is_string($id) ? trim($id) : '',
            $conversationIds
        ))));

        if ($conversationIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($conversationIds, 30) as $chunk) {
            $inList = implode(',', $chunk);
            $rows = $this->client->select($this->table(), [
                'select' => 'conversation_id,audio',
                'conversation_id' => 'in.('.$inList.')',
                'limit' => 1000,
            ]);
            foreach ($rows as $row) {
                $c = $row['conversation_id'] ?? null;
                $a = $row['audio'] ?? null;
                if (is_string($c) && is_string($a) && $a !== '') {
                    $out[] = $c;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
