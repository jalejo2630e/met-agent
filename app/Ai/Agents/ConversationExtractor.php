<?php

namespace App\Ai\Agents;

use App\Models\AgentExtractionVariable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agente de IA (Laravel AI SDK) que EXTRAE variables de recolección a partir de
 * una conversación de texto (WhatsApp/SMS). El esquema de salida se construye
 * dinámicamente con las variables definidas por el usuario para el agente.
 *
 * Usa el modelo más económico del proveedor por defecto para abaratar la
 * extracción, que puede correr tras cada mensaje.
 */
#[UseCheapestModel]
class ConversationExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  Collection<int, AgentExtractionVariable>|array<int, AgentExtractionVariable>  $variables
     */
    public function __construct(
        public iterable $variables = [],
        public string $agentName = '',
    ) {}

    public function instructions(): Stringable|string
    {
        $empresa = $this->agentName !== '' ? $this->agentName : 'la empresa';

        return <<<TEXT
        Eres un extractor de datos para {$empresa}. Recibirás el historial de una
        conversación de chat entre un cliente y un asistente. Tu tarea es EXTRAER
        los datos solicitados a partir de lo que el cliente haya dicho.

        Reglas:
        - Extrae únicamente información que el cliente haya expresado de forma
          explícita o claramente inferible. NO inventes ni asumas datos.
        - Si un dato aún no aparece en la conversación, déjalo vacío (cadena vacía
          para texto, o null cuando aplique). No rellenes con suposiciones.
        - Responde SIEMPRE en español y respeta el tipo pedido para cada variable.
        TEXT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $out = [];

        foreach ($this->variables as $var) {
            $name = (string) $var->name;
            if ($name === '') {
                continue;
            }

            $desc = trim((string) $var->description) !== ''
                ? (string) $var->description
                : (string) ($var->label ?: $name);

            $out[$name] = match ((string) $var->type) {
                'number' => $schema->number()->description($desc),
                'boolean' => $schema->boolean()->description($desc),
                default => $schema->string()->description($desc),
            };
        }

        // Evita un esquema vacío si no hay variables (no debería invocarse igual).
        if ($out === []) {
            $out['_'] = $schema->string()->description('sin variables definidas');
        }

        return $out;
    }
}
