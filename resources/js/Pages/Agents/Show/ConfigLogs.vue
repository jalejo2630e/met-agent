<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';
import WaveformAudioPlayer from '@/Components/WaveformAudioPlayer.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    agent: Object,
    endpointLogs: Array,
    contactLogs: { type: Array, default: () => [] },
    queueRuns: { type: Array, default: () => [] },
    postCallLogs: { type: Array, default: () => [] },
});

const expandedId = ref(null);

const filterStatus = ref('all'); // all, success, error

const filteredLogs = computed(() => {
    if (!props.endpointLogs) return [];
    if (filterStatus.value === 'success') return props.endpointLogs.filter(l => l.success);
    if (filterStatus.value === 'error') return props.endpointLogs.filter(l => !l.success);
    return props.endpointLogs;
});

const toggleExpand = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('es');
}

function truncate(str, len = 80) {
    if (!str) return '';
    const s = typeof str === 'object' ? JSON.stringify(str) : String(str);
    return s.length > len ? s.slice(0, len) + '...' : s;
}

const channelLabel = (ch) => ch === 'whatsapp' ? 'WhatsApp' : ch === 'call' ? 'Llamada' : ch;
const typeLabel = (t) => t === 'whatsapp' ? 'WhatsApp' : t === 'call' ? 'Llamada' : t;

/* ---------------- Post-Call ElevenLabs ---------------- */

const expandedPostCallId = ref(null);
const postCallView = ref({});        // id -> 'transcript' | 'analysis' | 'raw'
const audioByLogId = ref({});        // id -> base64
const loadingAudioId = ref(null);

const togglePostCall = (log) => {
    if (expandedPostCallId.value === log.id) {
        expandedPostCallId.value = null;
        return;
    }
    expandedPostCallId.value = log.id;
    // El audio suele llegar en un evento post_call_audio aparte: se busca por
    // conversation_id, no por si este evento concreto traía audio.
    if (log.conversation_id) loadPostCallAudio(log);
};

const currentView = (id) => postCallView.value[id] || 'transcript';
const setPostCallView = (id, view) => {
    postCallView.value = { ...postCallView.value, [id]: view };
};

/** ElevenLabs envía los datos en payload.data; algunos eventos van planos. */
const payloadData = (log) => {
    const p = log?.payload || {};
    return p && typeof p.data === 'object' && p.data !== null ? p.data : p;
};

const transcriptTurns = (log) => {
    const t = payloadData(log)?.transcript;
    return Array.isArray(t) ? t : [];
};

const analysisOf = (log) => {
    const a = payloadData(log)?.analysis;
    return a && typeof a === 'object' ? a : null;
};

const dataCollectionList = (log) => {
    const dc = analysisOf(log)?.data_collection_results;
    if (!dc || typeof dc !== 'object') return [];
    return Object.entries(dc).map(([key, item]) => ({
        id: (item && item.data_collection_id) || key,
        value: item && item.value !== undefined ? item.value : item,
        rationale: item && item.rationale,
    }));
};

const evaluationList = (log) => {
    const ec = analysisOf(log)?.evaluation_criteria_results;
    if (!ec || typeof ec !== 'object') return [];
    return Object.entries(ec).map(([key, item]) => ({
        id: (item && (item.criteria_id || item.criterion_id)) || key,
        result: item && item.result,
        rationale: item && item.rationale,
    }));
};

const fmtDuration = (secs) => {
    if (secs === null || secs === undefined) return '—';
    const m = Math.floor(secs / 60);
    const s = Math.floor(secs % 60);
    return `${m}:${String(s).padStart(2, '0')}`;
};

const roleLabel = (role) => role === 'agent' ? 'Agente' : role === 'user' ? 'Cliente' : (role || '—');

const loadPostCallAudio = async (log) => {
    // undefined = no intentado; string = base64; false = intentado sin audio.
    if (!log?.conversation_id || audioByLogId.value[log.id] !== undefined) return;
    loadingAudioId.value = log.id;
    try {
        const { data } = await axios.get(route('agents.elevenlabs.logs.audio', [props.agent.id, log.id]));
        audioByLogId.value = { ...audioByLogId.value, [log.id]: data.audio || false };
    } catch (e) {
        audioByLogId.value = { ...audioByLogId.value, [log.id]: false };
    } finally {
        loadingAudioId.value = null;
    }
};

const audioSrc = (raw) => {
    if (!raw) return null;
    return raw.startsWith('data:') ? raw : `data:audio/mpeg;base64,${raw}`;
};

const prettyJson = (obj) => {
    try { return JSON.stringify(obj, null, 2); } catch { return String(obj); }
};
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
        <div class="p-6">
            <!-- Post-Call ElevenLabs -->
            <div class="mb-8 border-b border-[#e3e8ee] pb-8">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Post-Call ElevenLabs</h3>
                        <p class="mt-1 text-sm text-[#444444]">
                            Todo lo recibido en el webhook post-call: transcripción, audio y variables de análisis de cada llamada.
                        </p>
                    </div>
                    <SecondaryButton @click="router.reload()">Actualizar</SecondaryButton>
                </div>

                <div class="mt-4">
                    <div v-if="!postCallLogs?.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-12 text-center text-[#444444]">
                        Aún no se han recibido eventos post-call de ElevenLabs.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="log in postCallLogs" :key="log.id" class="rounded-lg border border-[#e3e8ee]">
                            <!-- Cabecera -->
                            <div class="flex cursor-pointer flex-wrap items-center gap-2 px-4 py-3" @click="togglePostCall(log)">
                                <span :class="['h-2 w-2 shrink-0 rounded-full', log.call_successful === 'success' ? 'bg-green-500' : log.call_successful === 'failure' ? 'bg-red-500' : 'bg-gray-300']" />
                                <span class="text-sm font-medium text-[#133c75]">{{ log.phone || 'Sin teléfono' }}</span>
                                <span class="text-xs text-[#444444]">{{ formatDate(log.created_at) }}</span>
                                <span v-if="log.duration_secs != null" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ fmtDuration(log.duration_secs) }}</span>
                                <span v-if="log.call_successful" :class="['rounded px-1.5 py-0.5 text-xs font-medium', log.call_successful === 'success' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']">{{ log.call_successful === 'success' ? 'Éxito' : log.call_successful }}</span>
                                <span v-if="log.has_audio" class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700">Audio</span>
                                <svg :class="['ml-auto h-4 w-4 text-gray-400 transition-transform', expandedPostCallId === log.id && 'rotate-180']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>

                            <!-- Detalle -->
                            <div v-show="expandedPostCallId === log.id" class="border-t border-[#e3e8ee] px-4 py-4">
                                <div class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#444444]">
                                    <span v-if="log.conversation_id">conversation_id: <span class="font-mono text-[#133c75]">{{ log.conversation_id }}</span></span>
                                    <span v-if="log.event_type">tipo: {{ log.event_type }}</span>
                                    <span v-if="log.status">status: {{ log.status }}</span>
                                    <span v-if="log.cost != null">costo: {{ log.cost }}</span>
                                </div>

                                <!-- Audio: llega en un POST aparte (post_call_audio) y se relaciona
                                     por conversation_id, por eso se busca por ese id, no por el evento. -->
                                <div v-if="log.conversation_id" class="mb-4">
                                    <p v-if="loadingAudioId === log.id" class="text-xs text-gray-400">Cargando audio…</p>
                                    <WaveformAudioPlayer v-else-if="typeof audioByLogId[log.id] === 'string'" :src="audioSrc(audioByLogId[log.id])" />
                                    <p v-else-if="audioByLogId[log.id] === false" class="text-xs text-gray-400">
                                        Sin audio para esta conversación (aún no llegó el evento <code>post_call_audio</code> de ElevenLabs).
                                    </p>
                                </div>

                                <!-- Selector de vista -->
                                <div class="mb-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="v in [['transcript', 'Transcripción'], ['analysis', 'Análisis'], ['raw', 'JSON crudo']]"
                                        :key="v[0]"
                                        type="button"
                                        :class="['rounded-md px-2.5 py-1 text-xs font-medium transition', currentView(log.id) === v[0] ? 'bg-[var(--color-primary)] text-[var(--color-primary-foreground)]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']"
                                        @click="setPostCallView(log.id, v[0])"
                                    >
                                        {{ v[1] }}
                                    </button>
                                </div>

                                <!-- Transcripción -->
                                <div v-if="currentView(log.id) === 'transcript'">
                                    <div v-if="transcriptTurns(log).length" class="max-h-96 space-y-2 overflow-auto pr-1">
                                        <div
                                            v-for="(turn, idx) in transcriptTurns(log)"
                                            :key="idx"
                                            :class="['rounded-lg px-3 py-2', turn.role === 'agent' ? 'bg-[#f5f8fa]' : 'bg-indigo-50']"
                                        >
                                            <span class="text-xs font-medium" :class="turn.role === 'agent' ? 'text-[#444444]' : 'text-indigo-700'">{{ roleLabel(turn.role) }}</span>
                                            <p class="mt-0.5 whitespace-pre-wrap break-words text-sm text-gray-800">{{ turn.message }}</p>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-400">Sin transcripción en este evento.</p>
                                </div>

                                <!-- Análisis -->
                                <div v-else-if="currentView(log.id) === 'analysis'" class="space-y-3">
                                    <template v-if="analysisOf(log)">
                                        <div v-if="analysisOf(log).call_summary_title" class="rounded-lg bg-[#f5f8fa] px-3 py-2">
                                            <span class="text-xs font-medium text-gray-500">Título del resumen</span>
                                            <p class="mt-0.5 text-sm text-gray-800">{{ analysisOf(log).call_summary_title }}</p>
                                        </div>
                                        <div v-if="analysisOf(log).transcript_summary" class="rounded-lg bg-[#f5f8fa] px-3 py-2">
                                            <span class="text-xs font-medium text-gray-500">Resumen de la llamada</span>
                                            <p class="mt-0.5 whitespace-pre-wrap text-sm text-gray-800">{{ analysisOf(log).transcript_summary }}</p>
                                        </div>
                                        <div v-if="dataCollectionList(log).length">
                                            <span class="text-xs font-medium text-gray-500">Datos recolectados</span>
                                            <div class="mt-1 space-y-2">
                                                <div v-for="(item, idx) in dataCollectionList(log)" :key="idx" class="rounded-lg border border-[#e3e8ee] bg-white px-3 py-2">
                                                    <div class="flex items-baseline justify-between gap-2">
                                                        <span class="text-sm font-medium text-[#133c75]">{{ item.id }}</span>
                                                        <span class="text-sm font-semibold text-[#1976d2]">{{ item.value }}</span>
                                                    </div>
                                                    <p v-if="item.rationale" class="mt-1 text-xs text-gray-500">{{ item.rationale }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="evaluationList(log).length">
                                            <span class="text-xs font-medium text-gray-500">Criterios de evaluación</span>
                                            <div class="mt-1 space-y-2">
                                                <div v-for="(item, idx) in evaluationList(log)" :key="idx" class="rounded-lg border border-[#e3e8ee] bg-white px-3 py-2">
                                                    <div class="flex items-baseline justify-between gap-2">
                                                        <span class="text-sm font-medium text-[#133c75]">{{ item.id }}</span>
                                                        <span :class="['inline-flex rounded px-2 py-0.5 text-xs font-medium', item.result === 'success' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']">{{ item.result === 'success' ? 'Éxito' : item.result }}</span>
                                                    </div>
                                                    <p v-if="item.rationale" class="mt-1 text-xs text-gray-500">{{ item.rationale }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <p v-else class="text-sm text-gray-400">Sin variables de análisis en este evento.</p>
                                </div>

                                <!-- JSON crudo -->
                                <div v-else>
                                    <pre class="max-h-96 overflow-auto rounded bg-gray-900 p-3 text-xs leading-relaxed text-gray-100">{{ prettyJson(log.payload) }}</pre>
                                    <p class="mt-1 text-[11px] text-gray-400">El audio no se incluye aquí; se carga aparte por conversation_id.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-900">Logs de ejecución</h3>
            <p class="mt-1 text-sm text-[#444444]">
                Endpoints precargados, contactos WhatsApp/Llamadas registrados y ejecuciones de colas programadas (cron).
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <select
                    v-model="filterStatus"
                    class="rounded-md border-[#e3e8ee] text-sm"
                >
                    <option value="all">Todos</option>
                    <option value="success">Exitosos</option>
                    <option value="error">Con error</option>
                </select>
                <SecondaryButton @click="router.reload()">Actualizar</SecondaryButton>
            </div>

            <div class="mt-4">
                <div v-if="!filteredLogs.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-12 text-center text-[#444444]">
                    No hay logs de ejecución
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="log in filteredLogs"
                        :key="log.id"
                        class="rounded-lg border"
                        :class="log.success ? 'border-green-200 bg-green-50/50' : 'border-red-200 bg-red-50/50'"
                    >
                        <div
                            class="flex cursor-pointer items-center justify-between px-4 py-3"
                            @click="toggleExpand(log.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    :class="['h-2 w-2 rounded-full', log.success ? 'bg-green-500' : 'bg-red-500']"
                                />
                                <span class="text-sm font-medium">
                                    {{ log.endpoint?.name || 'Endpoint' }}
                                </span>
                                <span class="text-xs text-[#444444]">
                                    {{ log.request_method }} — {{ formatDate(log.created_at) }}
                                </span>
                                <span
                                    v-if="log.response_status"
                                    :class="['rounded px-1.5 py-0.5 text-xs font-mono', log.success ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800']"
                                >
                                    {{ log.response_status }}
                                </span>
                                <span v-if="log.error_message" class="max-w-xs truncate text-xs text-red-600">
                                    {{ log.error_message }}
                                </span>
                            </div>
                            <svg
                                :class="['h-4 w-4 text-gray-400 transition-transform', expandedId === log.id && 'rotate-180']"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div
                            v-show="expandedId === log.id"
                            class="border-t px-4 py-3 text-xs"
                            :class="log.success ? 'border-green-200' : 'border-red-200'"
                        >
                            <div class="grid gap-2 md:grid-cols-2">
                                <div v-if="log.request_url">
                                    <span class="font-medium text-gray-600">URL:</span>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/80 p-2">{{ log.request_url }}</pre>
                                </div>
                                <div v-if="log.error_message">
                                    <span class="font-medium text-red-600">Error:</span>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/80 p-2 text-red-700">{{ log.error_message }}</pre>
                                </div>
                                <div v-if="log.request_body" class="md:col-span-2">
                                    <span class="font-medium text-gray-600">Request body:</span>
                                    <pre class="mt-1 max-h-40 overflow-auto rounded bg-white/80 p-2">{{ truncate(log.request_body, 2000) }}</pre>
                                </div>
                                <div v-if="log.response_body" class="md:col-span-2">
                                    <span class="font-medium text-gray-600">Response:</span>
                                    <pre class="mt-1 max-h-64 overflow-auto rounded bg-white/80 p-2">{{ typeof log.response_body === 'object' ? JSON.stringify(log.response_body, null, 2) : log.response_body }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contactos WhatsApp / Llamadas -->
            <div class="mt-8">
                <h4 class="text-sm font-medium text-gray-700">Contactos WhatsApp / Llamadas</h4>
                <p class="mt-1 text-xs text-[#444444]">Registros de contacto con clientes (tu integración debe escribir en ClientContactLog para que aparezcan aquí).</p>
                <div class="mt-3">
                    <div v-if="!contactLogs?.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-8 text-center text-sm text-[#444444]">
                        No hay registros de contacto
                    </div>
                    <div v-else class="space-y-1.5">
                        <div
                            v-for="log in contactLogs"
                            :key="log.id"
                            class="flex items-center gap-3 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa]/50 px-3 py-2 text-sm"
                        >
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="log.channel === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                                {{ channelLabel(log.channel) }}
                            </span>
                            <span class="text-[#133c75]">{{ log.client?.name }} {{ log.client?.lastname }}</span>
                            <span class="text-[#444444]">{{ log.client?.phone || '—' }}</span>
                            <span class="ml-auto text-xs text-[#444444]">{{ formatDate(log.contacted_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ejecuciones de colas programadas (cron) -->
            <div class="mt-8">
                <h4 class="text-sm font-medium text-gray-700">Ejecuciones de colas programadas (cron)</h4>
                <p class="mt-1 text-xs text-[#444444]">Últimas colas de mensajes/llamadas ejecutadas por el cron (contact-queues:process).</p>
                <div class="mt-3">
                    <div v-if="!queueRuns?.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-8 text-center text-sm text-[#444444]">
                        No hay ejecuciones recientes
                    </div>
                    <div v-else class="space-y-1.5">
                        <div
                            v-for="run in queueRuns"
                            :key="run.id"
                            class="flex items-center gap-3 rounded-lg border border-[#e3e8ee] bg-white px-3 py-2 text-sm"
                        >
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="run.type === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                                {{ typeLabel(run.type) }}
                            </span>
                            <span class="rounded px-1.5 py-0.5 text-xs" :class="run.status === 'completed' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800'">
                                {{ run.status }}
                            </span>
                            <span class="text-[#444444]">{{ run.processed_count }}/{{ run.total }} procesados</span>
                            <span v-if="run.failed_count" class="text-xs text-red-600">{{ run.failed_count }} fallidos</span>
                            <span class="ml-auto text-xs text-[#444444]">{{ formatDate(run.last_run_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
