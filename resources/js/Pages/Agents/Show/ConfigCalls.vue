<script setup>
import { computed } from 'vue';
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
</script>

<template>
    <div class="space-y-6">
        <!-- Configuración de contacto por llamada -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Configuración de contacto por llamada</h3>
                <p class="mt-1 text-sm text-gray-500">
                    El <strong>webhook</strong> y el <strong>ID del agente de ElevenLabs</strong> se envían por POST en cada llamada.
                    El prompt del agente de voz se administra directamente en ElevenLabs. La configuración de campañas masivas está en la pestaña <strong>Campañas</strong>.
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
                            placeholder="Ej: abc123... (ID del agente de voz en ElevenLabs)"
                        />
                        <p class="mt-1 text-xs text-gray-500">Se envía junto con el webhook por POST en cada llamada.</p>
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
    </div>
</template>
