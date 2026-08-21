<script setup>
import { ref, computed, watch } from 'vue';
import { toast } from 'vue3-toastify';
import axios from 'axios';

/**
 * Panel de contacto por WhatsApp de un cliente:
 * - Pausar/reactivar la IA (toma de control humano).
 * - Responder con texto libre (mensaje de sesión, dentro de la ventana de 24h).
 * - Reabrir con una plantilla de Twilio/Meta si pasaron las 24h.
 * - Solicitudes de callback e historial del chat.
 * Auto-contenido.
 */
const props = defineProps({
    agent: { type: Object, required: true },
    client: { type: Object, default: null },
    plantillas: { type: Array, default: () => [] },
    defaultPlantillaId: { type: String, default: '' },
    active: { type: Boolean, default: false },
});

const selectedPlantillaId = ref('');
const sendingTemplate = ref(false);
const replyText = ref('');
const sendingText = ref(false);
const aiPaused = ref(false);
const togglingAi = ref(false);
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
            replyText.value = '';
            aiPaused.value = !!props.client?.ai_paused;
            loadMessages();
            loadCallbacks();
        }
    },
    { immediate: true }
);

async function toggleAiPause() {
    if (!props.client) return;
    const next = !aiPaused.value;
    togglingAi.value = true;
    try {
        const { data } = await axios.patch(route('agents.clients.ai-pause', [props.agent.id, props.client.id]), { paused: next });
        aiPaused.value = !!data.ai_paused;
        if (props.client) props.client.ai_paused = aiPaused.value;
        toast.success(aiPaused.value ? 'IA pausada: los mensajes los respondes tú.' : 'IA reactivada: vuelve a responder automáticamente.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo cambiar el estado de la IA.');
    } finally {
        togglingAi.value = false;
    }
}

async function sendText() {
    const client = props.client;
    const body = replyText.value.trim();
    if (!client?.phone || !body) return;
    sendingText.value = true;
    try {
        const { data } = await axios.post(route('agents.clients.whatsapp-send', [props.agent.id, client.id]), { body });
        if (data.success) {
            messages.value = [...messages.value, { type: 'ai', content: body, created_at: new Date().toISOString() }];
            replyText.value = '';
            toast.success(data.message || 'Mensaje enviado.');
        } else {
            toast.error(data.message || 'No se pudo enviar el mensaje.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo enviar el mensaje.');
    } finally {
        sendingText.value = false;
    }
}

async function sendTemplate() {
    const client = props.client;
    if (!client?.phone) return;
    if (!confirm('¿Enviar la plantilla de WhatsApp a este cliente?')) return;
    sendingTemplate.value = true;
    try {
        const payload = {};
        if (selectedPlantillaId.value) payload.id_plantilla = selectedPlantillaId.value;
        const { data } = await axios.post(route('agents.clients.initiate-whatsapp', [props.agent.id, client.id]), payload);
        if (data.success) {
            toast.success(data.message || 'Plantilla enviada.');
        } else {
            toast.error(data.message || 'No se pudo enviar la plantilla.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo enviar la plantilla.');
    } finally {
        sendingTemplate.value = false;
    }
}
</script>

<template>
    <div class="space-y-4 p-6">
        <!-- Pausar IA (toma de control humano) -->
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#e3e8ee] p-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-800">Respuesta automática de la IA</p>
                <p class="text-xs text-gray-500">Pausa la IA para responder tú manualmente. Al reactivar, la IA vuelve a contestar.</p>
            </div>
            <button
                type="button"
                :disabled="togglingAi || !client"
                :class="[
                    'inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition disabled:opacity-60',
                    aiPaused ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-green-100 text-green-800 hover:bg-green-200',
                ]"
                @click="toggleAiPause"
            >
                <span :class="['h-2 w-2 rounded-full', aiPaused ? 'bg-amber-500' : 'bg-green-500']" />
                {{ togglingAi ? '...' : (aiPaused ? 'IA pausada — reactivar' : 'IA activa — pausar') }}
            </button>
        </div>

        <!-- Responder (mensaje de sesión, dentro de 24h) -->
        <div v-if="client?.phone">
            <label class="mb-1 block text-xs font-medium text-gray-600">Responder por WhatsApp</label>
            <textarea
                v-model="replyText"
                rows="3"
                placeholder="Escribe una respuesta…"
                class="w-full rounded-md border-[#e3e8ee] text-sm"
            />
            <div class="mt-2 flex items-center justify-between gap-2">
                <p class="text-xs text-gray-400">Solo dentro de las 24h desde el último mensaje del cliente. Si pasaron, reabre con una plantilla abajo.</p>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#20BD5A] disabled:opacity-60"
                    :disabled="sendingText || !replyText.trim()"
                    @click="sendText"
                >
                    {{ sendingText ? 'Enviando…' : 'Enviar' }}
                </button>
            </div>
        </div>
        <p v-else class="text-sm text-amber-600">El cliente no tiene número de teléfono registrado.</p>

        <!-- Reabrir con plantilla (si pasaron 24h) -->
        <div class="border-t pt-4">
            <h4 class="text-sm font-medium text-gray-700">Reabrir conversación con plantilla</h4>
            <p class="mt-0.5 text-xs text-gray-500">Si pasaron más de 24h desde el último mensaje del cliente, inicia con una plantilla aprobada.</p>
            <div v-if="validPlantillas.length" class="mt-2 rounded border border-[#e3e8ee] bg-gray-50 p-3">
                <label class="mb-1 block text-xs font-medium text-gray-600">Plantilla</label>
                <select v-model="selectedPlantillaId" class="w-full rounded-md border-[#e3e8ee] text-sm">
                    <option value="">— Plantilla por defecto —</option>
                    <option v-for="p in validPlantillas" :key="p.id" :value="p.id">{{ p.name || p.id }}{{ p.from_twilio ? ' (Twilio)' : '' }}</option>
                </select>
            </div>
            <p v-else class="mt-2 rounded border border-dashed border-[#e3e8ee] bg-gray-50 p-3 text-xs text-gray-500">
                No hay plantillas configuradas. Agrégalas en <strong>Configuración → Mensajes → Plantillas</strong> (opción "Agregar desde Twilio").
            </p>
            <button
                v-if="client?.phone"
                type="button"
                class="mt-2 inline-flex items-center gap-2 rounded-lg border border-[#25D366] px-4 py-2 text-sm font-medium text-[#128C7E] transition hover:bg-[#25D366]/10 disabled:opacity-60"
                :disabled="sendingTemplate"
                @click="sendTemplate"
            >
                {{ sendingTemplate ? 'Enviando…' : 'Enviar plantilla' }}
            </button>
        </div>

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
