<script setup>
import { ref, computed, watch } from 'vue';
import { toast } from 'vue3-toastify';
import axios from 'axios';

/**
 * Panel de contacto por WhatsApp de un cliente: selector de plantilla, envío
 * (initiate-whatsapp → Twilio directo o webhook según backend), solicitudes de
 * callback e historial del chat. Auto-contenido.
 */
const props = defineProps({
    agent: { type: Object, required: true },
    client: { type: Object, default: null },
    plantillas: { type: Array, default: () => [] },
    defaultPlantillaId: { type: String, default: '' },
    active: { type: Boolean, default: false },
});

const selectedPlantillaId = ref('');
const sending = ref(false);
const messages = ref([]);
const loadingMessages = ref(false);
const callbackRequests = ref([]);
const loadingCallbacks = ref(false);
let loadedForClientId = null;

const validPlantillas = computed(
    () => (props.plantillas || []).filter((p) => (p?.id ?? '').toString().trim())
);

async function loadMessages() {
    messages.value = [];
    if (!props.client?.phone) return;
    loadingMessages.value = true;
    try {
        const { data } = await axios.get(route('agents.clients.whatsapp-messages', [props.agent.id, props.client.id]));
        messages.value = data.messages || [];
    } catch {
        messages.value = [];
    } finally {
        loadingMessages.value = false;
    }
}

async function loadCallbacks() {
    callbackRequests.value = [];
    if (!props.client) return;
    loadingCallbacks.value = true;
    try {
        const { data } = await axios.get(route('agents.callback-requests.index', props.agent.id), {
            params: { client_id: props.client.id },
        });
        callbackRequests.value = data.callback_requests || [];
    } catch {
        callbackRequests.value = [];
    } finally {
        loadingCallbacks.value = false;
    }
}

watch(
    () => (props.active && props.client ? props.client.id : null),
    (id) => {
        if (id && loadedForClientId !== id) {
            loadedForClientId = id;
            selectedPlantillaId.value = props.defaultPlantillaId || '';
            loadMessages();
            loadCallbacks();
        }
    },
    { immediate: true }
);

async function send() {
    const client = props.client;
    if (!client?.phone) return;
    if (!confirm('¿Enviar el mensaje de WhatsApp a este cliente?')) return;
    sending.value = true;
    try {
        const payload = {};
        if (selectedPlantillaId.value) payload.id_plantilla = selectedPlantillaId.value;
        const { data } = await axios.post(route('agents.clients.initiate-whatsapp', [props.agent.id, client.id]), payload);
        if (data.success) {
            toast.success(data.message || 'Mensaje de WhatsApp enviado.');
        } else {
            toast.error(data.message || 'No se pudo enviar el mensaje.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo enviar el mensaje.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="space-y-4 p-6">
        <div v-if="validPlantillas.length" class="rounded border border-[#e3e8ee] bg-gray-50 p-3">
            <label class="mb-1 block text-xs font-medium text-gray-600">Plantilla a enviar</label>
            <select v-model="selectedPlantillaId" class="w-full rounded-md border-[#e3e8ee] text-sm">
                <option value="">— Plantilla por defecto —</option>
                <option v-for="p in validPlantillas" :key="p.id" :value="p.id">{{ p.name || p.id }}{{ p.from_twilio ? ' (Twilio)' : '' }}</option>
            </select>
        </div>
        <p v-else class="rounded border border-dashed border-[#e3e8ee] bg-gray-50 p-3 text-xs text-gray-500">
            No hay plantillas configuradas. Agrégalas en <strong>Configuración → Mensajes → Plantillas</strong> (opción "Agregar desde Twilio").
        </p>

        <button
            v-if="client?.phone"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-white transition hover:bg-[#20BD5A] disabled:opacity-70"
            :disabled="sending"
            @click="send"
        >
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            {{ sending ? 'Enviando...' : 'Contactar usuario' }}
        </button>
        <p v-else class="text-sm text-amber-600">El cliente no tiene número de teléfono registrado.</p>

        <!-- Solicitudes de callback -->
        <div class="border-t pt-4">
            <h4 class="mb-2 text-sm font-medium text-gray-700">Solicitudes de callback (registro en cliente)</h4>
            <div v-if="loadingCallbacks" class="text-sm text-gray-500">Cargando...</div>
            <div v-else-if="!callbackRequests.length" class="text-sm text-gray-500">No hay solicitudes de callback para este cliente.</div>
            <div v-else class="max-h-32 space-y-1 overflow-y-auto rounded border border-gray-200 bg-gray-50 p-2 text-xs">
                <div
                    v-for="cb in callbackRequests"
                    :key="cb.id"
                    class="flex items-center justify-between rounded px-2 py-1"
                    :class="cb.status === 'pending' ? 'bg-amber-50' : cb.status === 'completed' ? 'bg-green-50' : 'bg-gray-50'"
                >
                    <span>{{ cb.scheduled_date }} {{ cb.scheduled_time || '' }} - {{ cb.channel === 'call' ? 'Llamada' : 'WhatsApp' }}</span>
                    <span class="font-medium" :class="cb.status === 'pending' ? 'text-amber-700' : cb.status === 'completed' ? 'text-green-700' : 'text-gray-500'">
                        {{ cb.status === 'pending' ? 'Pendiente' : cb.status === 'completed' ? 'Completado' : 'Cancelado' }}
                    </span>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Para programar un nuevo callback, ve a la pestaña <strong>Callbacks</strong>.</p>
        </div>

        <!-- Historial del chat -->
        <div class="border-t pt-4">
            <h4 class="mb-2 text-sm font-medium text-gray-700">Historial del chat</h4>
            <div v-if="loadingMessages" class="text-sm text-gray-500">Cargando mensajes...</div>
            <div v-else-if="!messages.length" class="text-sm text-gray-500">No hay mensajes en el historial.</div>
            <div v-else class="max-h-64 space-y-2 overflow-y-auto rounded border border-gray-200 bg-gray-50 p-3">
                <div
                    v-for="(m, i) in messages"
                    :key="i"
                    :class="['rounded-lg px-3 py-2 text-sm', m.type === 'ai' ? 'ml-6 bg-[#dcf8c6]' : 'ml-0 mr-6 border bg-white']"
                >
                    <span class="text-xs text-gray-500">{{ m.type === 'ai' ? 'Empresa' : 'Usuario' }}</span>
                    <p class="mt-0.5 whitespace-pre-wrap break-words">{{ m.content }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
