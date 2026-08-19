<script setup>
import { computed, ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const config = computed(() => props.agent?.call_config || {});
const sc = computed(() => config.value.schedule_config || {});

const form = useForm({
    webhook_url: config.value.webhook_url ?? '',
    elevenlabs_agent_id: config.value.elevenlabs_agent_id ?? '',
    schedule_config: {
        hours: sc.value.hours ?? { start: '09:00', end: '18:00' },
        days_of_week: sc.value.days_of_week ?? [],
        excluded_dates: sc.value.excluded_dates ?? [],
        campaign_slots: sc.value.campaign_slots ?? [],
        campaign_timezone: sc.value.campaign_timezone ?? 'America/Bogota',
        execution_rules: sc.value.execution_rules ?? [],
    },
});

const page = usePage();
const canEditWebhook = () => page.props.auth?.canAccessWebhooksAndTechnical === true;

const submit = () => {
    form.put(route('agents.call-config.update', props.agent));
};

// --- Prompt del sistema solo para llamadas (guardado en call_config) ---
function getInitialSections() {
    const pc = props.agent?.call_config?.prompt_configuration ?? {};
    const sections = pc.sections ?? {};
    const legacy = pc.system_prompt ?? '';
    return {
        greeting: sections.greeting ?? '',
        behavior: sections.behavior ?? '',
        business_rules: sections.business_rules ?? '',
        additional: sections.additional ?? (legacy && !pc.sections ? legacy : ''),
        tools: Array.isArray(sections.tools)
            ? sections.tools.map((t) => ({ name: t?.name ?? '', description: t?.description ?? '' }))
            : [],
    };
}

const sections = ref(getInitialSections());
watch(
    () => [props.agent?.id, props.agent?.call_config?.prompt_configuration],
    () => { sections.value = getInitialSections(); },
    { deep: true }
);

const promptErrors = computed(() => page.props.errors || {});

function syncPromptFormFromAgent() {
    sections.value = getInitialSections();
}

function addTool() {
    sections.value.tools = [...(sections.value.tools || []), { name: '', description: '' }];
}

function removeTool(index) {
    const t = [...(sections.value.tools || [])];
    t.splice(index, 1);
    sections.value.tools = t;
}

const submittingPrompt = ref(false);
const submitPrompt = () => {
    const payload = {
        sections: {
            greeting: sections.value.greeting ?? '',
            behavior: sections.value.behavior ?? '',
            business_rules: sections.value.business_rules ?? '',
            additional: sections.value.additional ?? '',
            tools: (sections.value.tools || []).map((t) => ({ name: t?.name ?? '', description: t?.description ?? '' })),
        },
    };
    submittingPrompt.value = true;
    router.put(route('agents.call-prompt-config.update', props.agent), payload, {
        preserveScroll: true,
        onSuccess: () => syncPromptFormFromAgent(),
        onFinish: () => { submittingPrompt.value = false; },
    });
};

const showPromptModal = ref(false);
const fullPromptText = computed(() => props.agent?.call_config?.prompt_configuration?.system_prompt ?? 'No hay prompt guardado.');

const securityRulesText = `• Solo responder sobre temas dentro del contexto definido en este prompt. No responder preguntas fuera de ese ámbito.
• No compartir información sensible, confidencial ni datos personales no autorizados. No inventar ni asumir datos.
• Si el usuario pregunta algo fuera del contexto, indicar de forma amable que solo puedes ayudar dentro del ámbito configurado.`;
</script>

<template>
    <div class="space-y-6">
        <!-- Configuración de contacto por llamada -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Configuración de contacto por llamada</h3>
                <p class="mt-1 text-sm text-gray-500">Webhook y horarios para contactar por llamada de voz. La configuración de campañas masivas está en la pestaña <strong>Campañas</strong>.</p>

                <form @submit.prevent="submit" class="mt-6 space-y-6">
                    <div v-if="canEditWebhook()">
                        <InputLabel value="Webhook URL" />
                        <TextInput v-model="form.webhook_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                        <InputError :message="form.errors.webhook_url" />
                    </div>

                    <div>
                        <InputLabel for="elevenlabs_agent_id" value="ID del agente (ElevenLabs)" />
                        <TextInput
                            id="elevenlabs_agent_id"
                            v-model="form.elevenlabs_agent_id"
                            type="text"
                            class="mt-1 block w-full font-mono"
                            placeholder="Ej: abc123... (ID del agente de voz en ElevenLabs)"
                        />
                        <p class="mt-1 text-xs text-gray-500">Si en el <code class="rounded bg-gray-100 px-1">.env</code> tienes <code class="rounded bg-gray-100 px-1">ELEVENLABS_API_KEY</code>, al guardar se enviará el prompt del sistema a este agente de voz.</p>
                        <InputError :message="form.errors.elevenlabs_agent_id" class="mt-1" />
                    </div>

                    <div>
                        <h4 class="text-sm font-medium text-gray-700">Horarios de contacto</h4>
                        <p class="mt-1 text-xs text-gray-500">Horario de ventana para llamadas manuales.</p>
                        <div class="mt-2 flex gap-4">
                            <div>
                                <InputLabel value="Desde" />
                                <input
                                    v-model="form.schedule_config.hours.start"
                                    type="time"
                                    class="mt-1 rounded-md border-[#e3e8ee] shadow-sm"
                                />
                            </div>
                            <div>
                                <InputLabel value="Hasta" />
                                <input
                                    v-model="form.schedule_config.hours.end"
                                    type="time"
                                    class="mt-1 rounded-md border-[#e3e8ee] shadow-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <PrimaryButton :disabled="form.processing">Guardar configuración</PrimaryButton>
                </form>
            </div>
        </div>

        <!-- Reglas de seguridad -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-[#f5f8fa]">
            <div class="border-b border-[#e3e8ee] bg-[#e3e8ee]/50 px-4 py-3">
                <h3 class="text-sm font-semibold text-[#33475b]">Reglas de seguridad (no modificables)</h3>
                <p class="mt-0.5 text-xs text-[#425b76]">Estas reglas se aplican siempre al prompt y no pueden ser editadas.</p>
            </div>
            <div class="p-4">
                <pre class="whitespace-pre-wrap text-sm text-[#33475b]">{{ securityRulesText }}</pre>
            </div>
        </div>

        <!-- Prompt del sistema -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="border-b border-[#e3e8ee] px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#33475b]">Prompt del sistema</h3>
                    <p class="mt-1 text-sm text-[#425b76]">Configura las secciones de la empresa para llamadas. Las reglas de seguridad se añaden automáticamente. Si tienes <code class="rounded bg-[#e3e8ee] px-1">ELEVENLABS_API_KEY</code>, al guardar el prompt también se enviará al agente de ElevenLabs.</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-[#e3e8ee] bg-white px-3 py-2 text-sm font-medium text-[#33475b] shadow-sm hover:bg-[#f5f8fa] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2"
                    @click="showPromptModal = true"
                >
                    Ver prompt completo
                </button>
            </div>
            <form @submit.prevent="submitPrompt" class="p-6 space-y-6">
                <div>
                    <InputLabel for="prompt-greeting-calls" value="Saludo" />
                    <textarea
                        id="prompt-greeting-calls"
                        v-model="sections.greeting"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Ej: Hola, soy el asistente de [empresa]. ¿En qué puedo ayudarte?"
                    />
                </div>
                <div>
                    <InputLabel for="prompt-behavior-calls" value="Comportamiento de la empresa" />
                    <textarea
                        id="prompt-behavior-calls"
                        v-model="sections.behavior"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Tono, estilo de respuesta..."
                    />
                </div>
                <div>
                    <InputLabel for="prompt-business_rules-calls" value="Reglas del negocio" />
                    <textarea
                        id="prompt-business_rules-calls"
                        v-model="sections.business_rules"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Políticas, límites, procedimientos..."
                    />
                </div>
                <div>
                    <InputLabel value="Herramientas (tools)" />
                    <p class="mt-0.5 text-xs text-[#425b76]">Indica el nombre y el uso de cada herramienta si aplica para el agente de voz.</p>
                    <div class="mt-2 space-y-3">
                        <div
                            v-for="(tool, idx) in (sections.tools || [])"
                            :key="idx"
                            class="flex flex-wrap items-start gap-2 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-3"
                        >
                            <TextInput v-model="sections.tools[idx].name" placeholder="Nombre de la tool" class="min-w-[140px] flex-1" />
                            <textarea
                                v-model="sections.tools[idx].description"
                                rows="2"
                                placeholder="Uso y cuándo invocarla"
                                class="min-w-0 flex-[2] rounded-md border-[#e3e8ee] text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                            <button type="button" class="rounded border border-red-200 px-2 py-1 text-sm text-red-600 hover:bg-red-50" @click="removeTool(idx)">Quitar</button>
                        </div>
                        <button type="button" class="text-sm font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]" @click="addTool">+ Agregar herramienta</button>
                    </div>
                </div>
                <div>
                    <InputLabel for="prompt-additional-calls" value="Adicionales" />
                    <textarea
                        id="prompt-additional-calls"
                        v-model="sections.additional"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        placeholder="Cualquier instrucción adicional..."
                    />
                </div>
                <div class="flex justify-end border-t border-[#e3e8ee] pt-4">
                    <PrimaryButton type="submit" :disabled="submittingPrompt">
                        {{ submittingPrompt ? 'Guardando...' : 'Guardar prompt' }}
                    </PrimaryButton>
                </div>
                <InputError v-if="promptErrors.sections" :message="Array.isArray(promptErrors.sections) ? promptErrors.sections[0] : promptErrors.sections" class="mt-2" />
            </form>
        </div>

        <!-- Modal prompt completo -->
        <Teleport to="body">
            <div v-show="showPromptModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="prompt-modal-title-calls">
                <div class="absolute inset-0 bg-black/50" @click="showPromptModal = false" />
                <div class="relative max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-lg border border-[#e3e8ee] bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-[#e3e8ee] bg-[#f5f8fa] px-4 py-3">
                        <h2 id="prompt-modal-title-calls" class="text-lg font-semibold text-[#33475b]">Prompt completo del sistema</h2>
                        <button type="button" class="rounded p-1 text-[#425b76] hover:bg-[#e3e8ee] hover:text-[#33475b] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]" aria-label="Cerrar" @click="showPromptModal = false">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="max-h-[70vh] overflow-y-auto p-4">
                        <pre class="whitespace-pre-wrap font-mono text-sm text-[#33475b]">{{ fullPromptText }}</pre>
                    </div>
                    <div class="border-t border-[#e3e8ee] bg-[#f5f8fa] px-4 py-3 text-right">
                        <button type="button" class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#33475b] shadow-sm hover:bg-[#e3e8ee] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2" @click="showPromptModal = false">Cerrar</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
