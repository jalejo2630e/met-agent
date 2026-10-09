<script setup>
import { ref, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            greeting: '',
            behavior: '',
            business_rules: '',
            additional: '',
            tools: [],
        }),
    },
});

const emit = defineEmits(['update:modelValue']);

function normalizeSections(v) {
    const d = defaultSections();
    if (!v || typeof v !== 'object') return d;
    return {
        greeting: v.greeting ?? d.greeting,
        behavior: v.behavior ?? d.behavior,
        business_rules: v.business_rules ?? d.business_rules,
        additional: v.additional ?? d.additional,
        tools: Array.isArray(v.tools) ? v.tools.map((t) => ({ name: t?.name ?? '', description: t?.description ?? '' })) : d.tools,
    };
}

const sections = ref(normalizeSections(props.modelValue));

watch(
    () => props.modelValue,
    (v) => { sections.value = normalizeSections(v); },
    { deep: true }
);

function defaultSections() {
    return {
        greeting: '',
        behavior: '',
        business_rules: '',
        additional: '',
        tools: [],
    };
}

function emitUpdate() {
    emit('update:modelValue', {
        greeting: sections.value.greeting ?? '',
        behavior: sections.value.behavior ?? '',
        business_rules: sections.value.business_rules ?? '',
        additional: sections.value.additional ?? '',
        tools: (sections.value.tools || []).map((t) => ({ name: t?.name ?? '', description: t?.description ?? '' })),
    });
}

function addTool() {
    sections.value.tools = [...(sections.value.tools || []), { name: '', description: '' }];
    emitUpdate();
}

function removeTool(index) {
    const t = [...(sections.value.tools || [])];
    t.splice(index, 1);
    sections.value.tools = t;
    emitUpdate();
}

const securityRulesText = `• Solo responder sobre temas dentro del contexto definido en este prompt. No responder preguntas fuera de ese ámbito.
• No compartir información sensible, confidencial ni datos personales no autorizados. No inventar ni asumir datos.
• Si el usuario pregunta algo fuera del contexto, indicar de forma amable que solo puedes ayudar dentro del ámbito configurado.`;
</script>

<template>
    <div class="space-y-6">
        <!-- Reglas de seguridad (solo lectura) -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-[#f5f8fa]">
            <div class="border-b border-[#e3e8ee] bg-[#e3e8ee]/50 px-4 py-3">
                <h3 class="text-sm font-semibold text-[#133c75]">Reglas de seguridad (no modificables)</h3>
                <p class="mt-0.5 text-xs text-[#444444]">
                    Estas reglas se aplican siempre al prompt de la empresa y no pueden ser editadas.
                </p>
            </div>
            <div class="p-4">
                <pre class="whitespace-pre-wrap text-sm text-[#133c75]">{{ securityRulesText }}</pre>
            </div>
        </div>

        <!-- Secciones editables -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="border-b border-[#e3e8ee] px-6 py-4">
                <h3 class="text-lg font-semibold text-[#133c75]">Prompt del sistema</h3>
                <p class="mt-1 text-sm text-[#444444]">
                    Configura las secciones que la empresa usará. Las reglas de seguridad anteriores se añaden automáticamente.
                </p>
            </div>
            <div class="space-y-6 p-6">
                <div>
                    <InputLabel for="prompt-greeting" value="Saludo" />
                    <textarea
                        id="prompt-greeting"
                        :value="sections.greeting"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Ej: Hola, soy el asistente de [empresa]. ¿En qué puedo ayudarte?"
                        @input="sections.greeting = $event.target.value; emitUpdate()"
                    />
                </div>

                <div>
                    <InputLabel for="prompt-behavior" value="Comportamiento de la empresa" />
                    <textarea
                        id="prompt-behavior"
                        :value="sections.behavior"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Tono, estilo de respuesta, cómo debe dirigirse al usuario..."
                        @input="sections.behavior = $event.target.value; emitUpdate()"
                    />
                </div>

                <div>
                    <InputLabel for="prompt-business_rules" value="Reglas del negocio" />
                    <textarea
                        id="prompt-business_rules"
                        :value="sections.business_rules"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Políticas, límites, procedimientos del negocio que la empresa debe seguir..."
                        @input="sections.business_rules = $event.target.value; emitUpdate()"
                    />
                </div>

                <div>
                    <InputLabel value="Herramientas del agente" />
                    <p class="mt-0.5 text-xs text-[#444444]">
                        Herramientas que el agente puede usar/mencionar; indica el nombre y el uso de cada una.
                    </p>
                    <div class="mt-2 space-y-3">
                        <div
                            v-for="(tool, idx) in (sections.tools || [])"
                            :key="idx"
                            class="flex flex-wrap items-start gap-2 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-3"
                        >
                            <TextInput
                                :model-value="sections.tools[idx].name"
                                placeholder="Nombre de la tool"
                                class="min-w-[140px] flex-1"
                                @update:model-value="sections.tools[idx].name = $event; emitUpdate()"
                            />
                            <textarea
                                :value="sections.tools[idx].description"
                                rows="2"
                                placeholder="Uso y cuándo invocarla"
                                class="min-w-0 flex-[2] rounded-md border-[#e3e8ee] text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                @input="sections.tools[idx].description = $event.target.value; emitUpdate()"
                            />
                            <button
                                type="button"
                                class="rounded border border-red-200 px-2 py-1 text-sm text-red-600 hover:bg-red-50"
                                @click="removeTool(idx)"
                            >
                                Quitar
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-sm font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]"
                            @click="addTool"
                        >
                            + Agregar herramienta
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel for="prompt-additional" value="Adicionales" />
                    <textarea
                        id="prompt-additional"
                        :value="sections.additional"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Cualquier instrucción adicional para la empresa..."
                        @input="sections.additional = $event.target.value; emitUpdate()"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
