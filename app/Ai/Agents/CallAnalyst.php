<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de IA (Laravel AI SDK) que analiza la transcripción de una llamada y
 * devuelve una salida estructurada: resumen, sentimiento, motivo, resultado y
 * si requiere alerta (con su categoría). El proveedor por defecto es el de
 * config('ai.default') (OpenAI). #[UseCheapestModel] usa el modelo más económico
 * del proveedor para abaratar el análisis masivo de llamadas.
 */
#[UseCheapestModel]
class CallAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $categorySlugs  Slugs de categorías de alerta disponibles.
     */
    public function __construct(
        public array $categorySlugs = [],
        public string $agentName = '',
    ) {
    }

    public function instructions(): Stringable|string
    {
        $categorias = $this->categorySlugs === []
            ? 'problema, alerta'
            : implode(', ', $this->categorySlugs);

        $empresa = $this->agentName !== '' ? $this->agentName : 'un agente de salud';

        return <<<TEXT
        Eres un analista de calidad de llamadas para {$empresa} (Centro Médico MET, sector salud y medicina deportiva).
        Recibirás la transcripción de una llamada entre un agente y un cliente/paciente.
        Analiza la conversación y responde SIEMPRE en español, de forma objetiva y concisa.

        Debes:
        - Resumir la llamada en 2-4 frases (qué pidió el cliente y cómo terminó).
        - Determinar el sentimiento general del cliente.
        - Identificar el motivo principal del contacto.
        - Determinar el resultado de la llamada.
        - Decidir si la llamada requiere una ALERTA para revisión humana (por ejemplo:
          cliente molesto, queja, riesgo para la salud, problema sin resolver o dato sensible).
        - Si requiere alerta, asignar la categoría más adecuada entre: {$categorias}.
          Si no requiere alerta, usa "ninguna".

        No inventes información que no esté en la transcripción.
        TEXT;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $categorias = array_values(array_unique([...$this->categorySlugs, 'ninguna']));

        return [
            'resumen' => $schema->string()
                ->description('Resumen de la llamada en 2-4 frases, en español.')
                ->required(),
            'sentimiento' => $schema->string()
                ->description('Sentimiento general del cliente durante la llamada.')
                ->enum(['positivo', 'neutral', 'negativo'])
                ->required(),
            'motivo_contacto' => $schema->string()
                ->description('Motivo principal por el que se dio la llamada.')
                ->required(),
            'resultado' => $schema->string()
                ->description('Cómo terminó la llamada.')
                ->enum(['resuelto', 'pendiente', 'agendado', 'rechazado', 'no_contactado', 'otro'])
                ->required(),
            'requiere_alerta' => $schema->boolean()
                ->description('true si la llamada debe ser revisada por un humano.')
                ->required(),
            'categoria_alerta' => $schema->string()
                ->description('Categoría de la alerta; "ninguna" si no requiere alerta.')
                ->enum($categorias)
                ->required(),
            'descripcion_alerta' => $schema->string()
                ->description('Explicación breve del motivo de la alerta. Vacío si no aplica.'),
        ];
    }
}
