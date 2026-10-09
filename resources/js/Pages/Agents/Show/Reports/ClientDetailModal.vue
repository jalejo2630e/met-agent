<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import WaveformAudioPlayer from '@/Components/WaveformAudioPlayer.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    agent: { type: Object, required: true },
    clientId: { type: [Number, String], default: null },
    clientName: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const page = usePage();

const loading = ref(false);
const detail = ref(null);

const STATUS_LABELS = {
    no_contactado: 'No contactado',
    llamada_programada: 'Llamada programada',
    contactado: 'Contactado',
    en_proceso: 'En proceso',
    no_interesado: 'No interesado',
};

const client = computed(() => detail.value?.client || null);
const progress = computed(() => detail.value?.progress || null);
const contactLogs = computed(() => detail.value?.contact_logs || []);
const notes = computed(() => detail.value?.notes || []);
const callAlerts = computed(() => detail.value?.call_alerts || []);
const alertCategories = computed(() => detail.value?.alert_categories || []);

const statusLabel = computed(() => {
    const s = client.value?.status;
    return s ? (STATUS_LABELS[s] || s) : '—';
});

const progressSummary = computed(() => {
    const p = progress.value;
    if (!p) return null;
    if (p.status === 'completed') return { text: 'Proceso completado', color: '#2e7d32' };
    if (p.status === 'not_started') return { text: 'Sin iniciar', color: '#9e9e9e' };
    return { text: `Tema actual: ${p.current_topic} · ${p.current_pct ?? 0}%`, color: '#1976d2' };
});

const initials = computed(() => {
    const full = `${client.value?.name || ''} ${client.value?.lastname || ''}`.trim();
    if (!full) return '?';
    return full.split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase();
});

/* ---- Tabs ---- */
const activeTab = ref('llamadas');
const tabList = computed(() => [
    { key: 'avance', label: 'Avance' },
    { key: 'llamadas', label: 'Llamadas', count: calls.value.length },
    { key: 'alertas', label: 'Alertas', count: callAlerts.value.length },
    { key: 'notas', label: 'Notas', count: notes.value.length },
]);

/* ---- Carga del detalle ---- */
async function fetchDetail() {
    if (!props.clientId) return;
    loading.value = true;
    detail.value = null;
    activeTab.value = 'llamadas';
    resetCalls();
    try {
        const { data } = await axios.get(route('agents.clients.detail', [props.agent.id, props.clientId]));
        detail.value = data;
        fetchCalls();
    } catch (e) {
        toast.error('No se pudo cargar el detalle del cliente.');
    } finally {
        loading.value = false;
    }
}

watch(() => [props.show, props.clientId], ([show]) => {
    if (show && props.clientId) fetchDetail();
});

/* ---- Notas ---- */
const newNote = ref('');
const savingNote = ref(false);

async function addNote() {
    if (!newNote.value.trim()) return;
    savingNote.value = true;
    try {
        const { data } = await axios.post(route('agents.clients.notes.store', [props.agent.id, props.clientId]), {
            body: newNote.value.trim(),
        });
        if (detail.value) detail.value.notes = data.notes;
        newNote.value = '';
    } catch (e) {
        toast.error('No se pudo guardar la nota.');
    } finally {
        savingNote.value = false;
    }
}

async function deleteNote(note) {
    if (!confirm('¿Eliminar esta nota?')) return;
    try {
        const { data } = await axios.delete(route('agents.clients.notes.destroy', [props.agent.id, props.clientId, note.id]));
        if (detail.value) detail.value.notes = data.notes;
    } catch (e) {
        toast.error('No se pudo eliminar la nota.');
    }
}

/* ---- Alertas de llamada ---- */
async function updateAlertCategory(alert, event) {
    const raw = event.target.value;
    const categoriaId = raw === '' ? null : Number(raw);
    try {
        const { data } = await axios.patch(
            route('agents.clients.call-alerts.update', [props.agent.id, props.clientId, alert.id]),
            { alerta_categoria_id: categoriaId },
        );
        if (detail.value) detail.value.call_alerts = data.call_alerts;
    } catch (e) {
        toast.error('No se pudo actualizar la categoría.');
    }
}

async function deleteAlert(alert) {
    if (!confirm('¿Eliminar esta alerta de llamada?')) return;
    try {
        const { data } = await axios.delete(route('agents.clients.call-alerts.destroy', [props.agent.id, props.clientId, alert.id]));
        if (detail.value) detail.value.call_alerts = data.call_alerts;
    } catch (e) {
        toast.error('No se pudo eliminar la alerta.');
    }
}

/* ---- Llamadas / transcripciones ---- */
const calls = ref([]);
const loadingCalls = ref(false);
const callsMessage = ref('');
const selectedCallId = ref(null);
const callDetailById = ref({});
const loadingCallDetail = ref(null);

function transcriptParams() {
    const p = {};
    const campana = page.props.campana_ainoa;
    if (campana) p.campana = campana;
    if (client.value?.phone) p.phone = client.value.phone;
    return p;
}

function resetCalls() {
    calls.value = [];
    callsMessage.value = '';
    selectedCallId.value = null;
    callDetailById.value = {};
}

async function fetchCalls() {
    if (!client.value?.phone) {
        callsMessage.value = 'Cliente sin teléfono';
        return;
    }
    loadingCalls.value = true;
    try {
        const { data } = await axios.get(route('agents.clients.call-transcript', [props.agent.id, props.clientId]), {
            params: transcriptParams(),
        });
        calls.value = data.calls || [];
        callsMessage.value = data.message || '';
    } catch (e) {
        callsMessage.value = 'Error al cargar las llamadas';
    } finally {
        loadingCalls.value = false;
    }
}

async function selectCall(call) {
    // Maestro-detalle: seleccionar siempre muestra la conversación a la derecha.
    selectedCallId.value = call.id;
    if (callDetailById.value[call.id]) return;
    loadingCallDetail.value = call.id;
    try {
        const { data } = await axios.get(route('agents.clients.call-transcript', [props.agent.id, props.clientId]), {
            params: { call_id: call.id, ...transcriptParams() },
        });
        if (data.call) {
            // Si no vino el audio y hay audio disponible, pedirlo aparte.
            if (!data.call.audio && call.has_audio && call.conversation_id) {
                try {
                    const a = await axios.get(route('agents.clients.call-audio', [props.agent.id, props.clientId]), {
                        params: { conversation_id: call.conversation_id, ...transcriptParams() },
                    });
                    data.call.audio = a.data?.audio || null;
                } catch (_) { /* sin audio */ }
            }
            callDetailById.value = { ...callDetailById.value, [call.id]: data.call };
        }
    } finally {
        loadingCallDetail.value = null;
    }
}

function audioSrc(raw) {
    if (!raw) return null;
    return raw.startsWith('data:') ? raw : `data:audio/mpeg;base64,${raw}`;
}

function callVars(call) {
    const v = call?.variables_extraidas;
    if (!v || typeof v !== 'object') return null;
    return {
        title: v.call_summary_title || null,
        summary: v.transcript_summary || null,
        successful: v.call_successful ?? null,
    };
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
function fmtDuration(seg) {
    if (!seg && seg !== 0) return '—';
    const m = Math.floor(seg / 60);
    const s = Math.round(seg % 60);
    return `${m}:${String(s).padStart(2, '0')}`;
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-6">
            <div class="my-4 flex max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <!-- Header -->
                <div class="flex items-start justify-between gap-3 border-b border-[#e3e8ee] bg-[#f9fbfc] px-6 py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#eef2f6] text-sm font-semibold text-[#133c75]">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-[#133c75]">
                                {{ client ? `${client.name} ${client.lastname || ''}`.trim() : (clientName || 'Cliente') }}
                            </h2>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#444444]">
                                <span v-if="client?.phone" class="inline-flex items-center gap-1">📞 {{ client.phone }}</span>
                                <span v-if="client?.email" class="inline-flex max-w-[220px] items-center gap-1 truncate">✉️ {{ client.email }}</span>
                                <span v-if="client?.document" class="inline-flex items-center gap-1">🪪 {{ client.document_type }} {{ client.document }}</span>
                                <span class="inline-flex items-center rounded-full bg-[#eef2f6] px-2 py-0.5 font-medium">{{ statusLabel }}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="shrink-0 rounded p-1.5 text-[#444444] hover:bg-[#eef2f6]" @click="emit('close')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div v-if="loading" class="p-12 text-center text-[#444444]">Cargando detalle...</div>

                <template v-else-if="detail">
                    <!-- Tabs -->
                    <nav class="flex gap-1 overflow-x-auto border-b border-[#e3e8ee] bg-[#f9fbfc] px-4">
                        <button
                            v-for="t in tabList"
                            :key="t.key"
                            type="button"
                            class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition"
                            :class="activeTab === t.key ? 'border-[var(--color-primary)] text-[#133c75]' : 'border-transparent text-[#98a4b3] hover:text-[#444444]'"
                            @click="activeTab = t.key"
                        >
                            {{ t.label }}
                            <span v-if="t.count" class="rounded-full bg-[#eef2f6] px-1.5 text-[11px] font-semibold text-[#444444]">{{ t.count }}</span>
                        </button>
                    </nav>

                    <!-- Contenido de la pestaña activa (carga perezosa) -->
                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <!-- Avance del proceso -->
                        <div v-if="activeTab === 'avance'">
                            <div v-if="progress" class="rounded-lg border border-[#eef2f6] p-4">
                                <p v-if="progressSummary" class="mb-3 text-sm font-medium" :style="{ color: progressSummary.color }">
                                    {{ progressSummary.text }}
                                </p>
                                <div class="space-y-3">
                                    <div v-for="t in progress.topics" :key="t.field">
                                        <div class="mb-1 flex items-center justify-between text-sm">
                                            <span class="flex items-center gap-2 text-[#133c75]">
                                                <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: t.color }" />
                                                {{ t.label }}
                                                <span v-if="t.completed" class="inline-flex items-center gap-1 rounded-full bg-[#e8f5e9] px-2 py-0.5 text-[11px] font-medium text-[#2e7d32]">✓ Completado</span>
                                                <span v-else-if="progress.current_topic === t.label" class="inline-flex items-center rounded-full bg-[#e3f2fd] px-2 py-0.5 text-[11px] font-medium text-[#1976d2]">En curso</span>
                                            </span>
                                            <span class="text-xs text-[#444444]">{{ t.value }} / {{ t.max }}</span>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-[#eef2f6]">
                                            <div class="h-full rounded-full transition-all" :style="{ width: t.pct + '%', backgroundColor: t.color }" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="py-10 text-center text-sm text-[#98a4b3]">No hay avance de proceso configurado.</p>
                        </div>

                        <!-- Llamadas y transcripciones (maestro-detalle) -->
                        <div v-else-if="activeTab === 'llamadas'" class="space-y-4">
                            <div v-if="loadingCalls" class="rounded-lg border border-[#eef2f6] p-6 text-center text-sm text-[#444444]">Cargando llamadas...</div>
                            <div v-else-if="!calls.length" class="rounded-lg border border-[#eef2f6] p-6 text-center text-sm text-[#98a4b3]">{{ callsMessage || 'Sin llamadas registradas.' }}</div>
                            <div v-else class="grid gap-3 md:grid-cols-[minmax(0,240px)_1fr]">
                                <!-- Lista de llamadas (izquierda) -->
                                <ul class="max-h-[54vh] divide-y divide-[#eef2f6] overflow-y-auto rounded-lg border border-[#eef2f6]">
                                    <li v-for="c in calls" :key="c.id">
                                        <button
                                            type="button"
                                            class="flex w-full flex-col items-start gap-0.5 border-l-2 px-3 py-2.5 text-left transition"
                                            :class="selectedCallId === c.id ? 'border-[var(--color-primary)] bg-[#f0f4f8]' : 'border-transparent hover:bg-[#f5f8fa]'"
                                            @click="selectCall(c)"
                                        >
                                            <span class="flex items-center gap-2 text-sm text-[#133c75]">
                                                <svg class="h-4 w-4 shrink-0 text-[#f9a825]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                                {{ fmtDate(c.created_at) }}
                                            </span>
                                            <span class="pl-6 text-xs text-[#98a4b3]">{{ fmtDuration(c.duration_call_seg) }}<span v-if="c.has_audio"> · 🔊</span></span>
                                        </button>
                                    </li>
                                </ul>

                                <!-- Conversación de la llamada seleccionada (derecha) -->
                                <div class="max-h-[54vh] min-h-[240px] overflow-y-auto rounded-lg border border-[#eef2f6] p-4">
                                    <div v-if="!selectedCallId" class="flex h-full min-h-[200px] items-center justify-center text-center text-sm text-[#98a4b3]">
                                        Selecciona una llamada de la izquierda para ver la conversación.
                                    </div>
                                    <div v-else-if="loadingCallDetail === selectedCallId" class="py-10 text-center text-sm text-[#444444]">Cargando transcripción...</div>
                                    <template v-else-if="callDetailById[selectedCallId]">
                                        <WaveformAudioPlayer v-if="callDetailById[selectedCallId].audio" :src="audioSrc(callDetailById[selectedCallId].audio)" class="mb-3" />
                                        <div v-if="callVars(callDetailById[selectedCallId])" class="mb-3 rounded-md bg-[#f9fbfc] p-3 text-sm">
                                            <p v-if="callVars(callDetailById[selectedCallId]).title" class="font-medium text-[#133c75]">{{ callVars(callDetailById[selectedCallId]).title }}</p>
                                            <p v-if="callVars(callDetailById[selectedCallId]).summary" class="mt-1 text-[#444444]">{{ callVars(callDetailById[selectedCallId]).summary }}</p>
                                        </div>
                                        <div v-if="(callDetailById[selectedCallId].transcript || []).length" class="space-y-2">
                                            <div
                                                v-for="(turn, ti) in callDetailById[selectedCallId].transcript"
                                                :key="ti"
                                                class="flex"
                                                :class="turn.role === 'agent' ? 'justify-start' : 'justify-end'"
                                            >
                                                <div
                                                    class="max-w-[85%] rounded-lg px-3 py-1.5 text-sm"
                                                    :class="turn.role === 'agent' ? 'bg-[#eef2f6] text-[#133c75]' : 'bg-[#e3f2fd] text-[#0d47a1]'"
                                                >
                                                    {{ turn.message }}
                                                </div>
                                            </div>
                                        </div>
                                        <p v-else class="text-sm text-[#98a4b3]">Sin transcripción disponible.</p>
                                    </template>
                                </div>
                            </div>

                            <!-- Tracking de contactos -->
                            <div v-if="contactLogs.length">
                                <p class="mb-1 text-xs font-medium text-[#444444]">Historial de contacto ({{ contactLogs.length }})</p>
                                <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#eef2f6] p-2">
                                    <div v-for="(l, i) in contactLogs" :key="i" class="flex items-center justify-between px-2 py-1 text-xs text-[#444444]">
                                        <span class="inline-flex items-center gap-1">
                                            <span v-if="l.channel === 'call'">📞 Llamada</span>
                                            <span v-else-if="l.channel === 'whatsapp'">💬 WhatsApp</span>
                                            <span v-else>{{ l.channel }}</span>
                                        </span>
                                        <span>{{ fmtDate(l.contacted_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alertas de llamada -->
                        <div v-else-if="activeTab === 'alertas'">
                            <div class="rounded-lg border border-[#eef2f6]">
                                <p v-if="!callAlerts.length" class="p-6 text-center text-sm text-[#98a4b3]">Sin alertas de llamada registradas.</p>
                                <ul v-else class="divide-y divide-[#eef2f6]">
                                    <li v-for="a in callAlerts" :key="a.id" class="p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#444444]">
                                                    <span v-if="a.phone">📞 {{ a.phone }}</span>
                                                    <span>Llamada #{{ a.numero_llamada ?? '—' }}</span>
                                                    <span>Paso {{ a.paso_llamada ?? '—' }}</span>
                                                    <span class="text-[#98a4b3]">{{ fmtDate(a.created_at) }}</span>
                                                </div>
                                                <p v-if="a.descripcion" class="mt-2 whitespace-pre-wrap text-sm text-[#133c75]">{{ a.descripcion }}</p>
                                            </div>
                                            <button type="button" class="shrink-0 rounded p-1 text-[#c2cddb] hover:bg-red-50 hover:text-red-500" title="Eliminar alerta" @click="deleteAlert(a)">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span
                                                v-if="a.categoria"
                                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                                :style="{ backgroundColor: (a.categoria.color || '#eef2f6') + '22', color: a.categoria.color || '#444444' }"
                                            >
                                                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: a.categoria.color || '#c2cddb' }" />
                                                {{ a.categoria.nombre }}
                                            </span>
                                            <select
                                                class="rounded-md border-[#e3e8ee] py-1 text-xs text-[#133c75] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                                :value="a.categoria ? a.categoria.id : ''"
                                                @change="updateAlertCategory(a, $event)"
                                            >
                                                <option value="">Sin categoría</option>
                                                <option v-for="cat in alertCategories" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                                            </select>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div v-else-if="activeTab === 'notas'">
                            <div class="flex items-start gap-2">
                                <textarea
                                    v-model="newNote"
                                    rows="2"
                                    placeholder="Agregar una nota..."
                                    class="flex-1 rounded-md border-[#e3e8ee] text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                />
                                <button
                                    type="button"
                                    :disabled="savingNote || !newNote.trim()"
                                    class="rounded-md bg-[var(--color-primary)] px-3 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-50"
                                    @click="addNote"
                                >
                                    {{ savingNote ? '...' : 'Agregar' }}
                                </button>
                            </div>
                            <ul v-if="notes.length" class="mt-3 space-y-2">
                                <li v-for="n in notes" :key="n.id" class="rounded-md bg-[#f9fbfc] p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="whitespace-pre-wrap text-sm text-[#133c75]">{{ n.body }}</p>
                                        <button type="button" class="shrink-0 rounded p-1 text-[#c2cddb] hover:bg-red-50 hover:text-red-500" @click="deleteNote(n)">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-[11px] text-[#98a4b3]">
                                        {{ n.author || 'Usuario' }} · {{ fmtDate(n.created_at) }}
                                    </p>
                                </li>
                            </ul>
                            <p v-else class="mt-3 text-xs text-[#98a4b3]">Aún no hay notas.</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>
