<script setup>
import { computed, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const config = computed(() => props.agent?.call_config || {});
const sc = computed(() => config.value.schedule_config || {});

const form = useForm({
    webhook_url: config.value.webhook_url ?? '',
    elevenlabs_agent_id: config.value.elevenlabs_agent_id ?? '',
    elevenlabs_phone_number_id: config.value.elevenlabs_phone_number_id ?? '',
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

// Webhook post-call para pegar en ElevenLabs (recibe transcripciones/audio y libera la cola).
const appOrigin = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''));
const postCallWebhookUrl = computed(() => `${appOrigin.value}/api/agents/${props.agent?.id}/elevenlabs/post-call`);
const webhookCopied = ref(false);
async function copyPostCallWebhook() {
    try {
        await navigator.clipboard.writeText(postCallWebhookUrl.value);
        webhookCopied.value = true;
        setTimeout(() => { webhookCopied.value = false; }, 2000);
    } catch (e) {
        // El usuario puede copiar manualmente si el navegador bloquea el portapapeles.
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Configuración de contacto por llamada -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Configuración de contacto por llamada</h3>
                <p class="mt-1 text-sm text-gray-500">
                    El <strong>webhook</strong>, el <strong>ID del agente</strong> y el <strong>ID del número (phone number)</strong> de ElevenLabs
                    se envían por POST en cada llamada, junto con los datos del cliente y sus variables dinámicas.
                    El prompt del agente de voz se administra directamente en ElevenLabs.
                </p>

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
                            placeholder="Ej: agent_abc123..."
                        />
                        <InputError :message="form.errors.elevenlabs_agent_id" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="elevenlabs_phone_number_id" value="ID del número · phone number id (ElevenLabs)" />
                        <TextInput
                            id="elevenlabs_phone_number_id"
                            v-model="form.elevenlabs_phone_number_id"
                            type="text"
                            class="mt-1 block w-full font-mono"
                            placeholder="Ej: phnum_abc123..."
                        />
                        <p class="mt-1 text-xs text-gray-500">Número saliente de ElevenLabs. Se envía junto con el ID del agente por POST en cada llamada.</p>
                        <InputError :message="form.errors.elevenlabs_phone_number_id" class="mt-1" />
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

        <!-- Webhook Post-Call de ElevenLabs -->
        <div class="overflow-hidden rounded-lg border border-emerald-200 bg-white">
            <div class="border-b border-emerald-200 bg-emerald-50/50 px-6 py-4">
                <h3 class="text-lg font-semibold text-[#33475b]">Webhook Post-Call (ElevenLabs)</h3>
                <p class="mt-1 text-sm text-[#425b76]">
                    Pega esta URL en ElevenLabs → <em>Conversational AI → Post-call webhook</em>. Recibe la
                    <strong>transcripción, el audio y el análisis</strong> de cada llamada, y
                    <strong>libera la cola</strong> para ejecutar las siguientes (de a 10).
                </p>
            </div>
            <div class="space-y-3 p-6">
                <InputLabel value="URL del webhook post-call" />
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        :value="postCallWebhookUrl"
                        readonly
                        class="min-w-0 flex-1 rounded-md border-[#e3e8ee] bg-[#f5f8fa] font-mono text-sm text-[#33475b]"
                        @focus="(e) => e.target.select()"
                    />
                    <button
                        type="button"
                        class="shrink-0 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        @click="copyPostCallWebhook"
                    >
                        {{ webhookCopied ? '¡Copiado!' : 'Copiar' }}
                    </button>
                </div>
                <p class="text-xs text-[#64748b]">
                    Las llamadas recibidas quedan en la pestaña <strong>Logs → Post-Call ElevenLabs</strong>.
                </p>
            </div>
        </div>
    </div>
</template>
