<?php

namespace App\Services;

class AgentPromptBuilder
{
    /**
     * Reglas de seguridad que no pueden ser modificadas por el usuario.
     * Se inyectan al inicio del prompt del sistema.
     */
    public static function getSecurityRulesPrefix(): string
    {
        return <<<'TEXT'
## Reglas de seguridad (no modificables)
- Solo debes responder sobre temas que estén dentro del contexto definido en este prompt. No respondas preguntas que se salgan de ese ámbito.
- No compartas información sensible, confidencial ni datos personales que no estén autorizados. No inventes ni asumas datos que no tengas.
- Si el usuario pregunta algo fuera del contexto definido, indícale de forma amable que solo puedes ayudar dentro del ámbito indicado en tu configuración.
---
TEXT;
    }

    /**
     * Reglas de seguridad al cierre (refuerzo).
     */
    public static function getSecurityRulesSuffix(): string
    {
        return "\n\nRecuerda: mantén siempre las reglas de seguridad anteriores en todas tus respuestas.";
    }

    /**
     * Construye el system_prompt completo a partir de las secciones editables.
     *
     * @param  array{greeting?: string, behavior?: string, business_rules?: string, additional?: string, tools?: array<{name: string, description: string}>}  $sections
     */
    public static function buildFromSections(array $sections): string
    {
        $parts = [self::getSecurityRulesPrefix()];

        $greeting = trim((string) ($sections['greeting'] ?? ''));
        if ($greeting !== '') {
            $parts[] = "## Saludo\n" . $greeting;
        }

        $behavior = trim((string) ($sections['behavior'] ?? ''));
        if ($behavior !== '') {
            $parts[] = "## Comportamiento del agente\n" . $behavior;
        }

        $businessRules = trim((string) ($sections['business_rules'] ?? ''));
        if ($businessRules !== '') {
            $parts[] = "## Reglas del negocio\n" . $businessRules;
        }

        $tools = $sections['tools'] ?? [];
        if (is_array($tools) && count($tools) > 0) {
            $toolsText = "## Herramientas (tools)\n";
            foreach ($tools as $tool) {
                $name = trim((string) ($tool['name'] ?? ''));
                $description = trim((string) ($tool['description'] ?? ''));
                if ($name !== '' || $description !== '') {
                    $toolsText .= "- **" . ($name !== '' ? $name : 'Tool') . "**: " . ($description !== '' ? $description : 'Sin descripción') . "\n";
                }
            }
            $parts[] = trim($toolsText);
        }

        $additional = trim((string) ($sections['additional'] ?? ''));
        if ($additional !== '') {
            $parts[] = "## Adicionales\n" . $additional;
        }

        $parts[] = self::getSecurityRulesSuffix();

        return implode("\n\n", $parts);
    }
}
