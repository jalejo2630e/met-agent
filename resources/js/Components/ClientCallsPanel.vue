<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import WaveformAudioPlayer from '@/Components/WaveformAudioPlayer.vue';

/**
 * Panel de historial de llamadas de un cliente: lista de llamadas, transcripción,
 * audio (ondas, carga automática) y variables de análisis de ElevenLabs.
 * Auto-contenido: carga sus datos cuando está activo y hay cliente.
 */
const props = defineProps({
    agent: { type: Object, required: true },
    client: { type: Object, default: null },
    active: { type: Boolean, default: false },
});

const page = usePage();

const loadingTranscript = ref(false);
const transcriptData = ref(null);
const selectedCallIndex = ref(0);
const loadedCallsById = ref({});
const loadingCallDetail = ref(null);
const fetchedAudioByConversationId = ref({});
const loadingAudio = ref(null);
let loadedForClientId = null;

const transcriptApiParams = (client) => {
    const p = {};
    const campana = page.props.campana_ainoa;
    if (campana) p.campana = campana;
    if (client?.phone) p.phone = client.phone;
    return p;
};

async function loadCalls() {
    const client = props.client;
    transcriptData.value = null;
    selectedCallIndex.value = 0;
    loadedCallsById.value = {};
    loadingCallDetail.value = null;
    fetchedAudioByConversationId.value = {};
    loadingAudio.value = null;
    if (!client) return;
    if (!client.phone) {
        transcriptData.value = { client, calls: [], message: 'El cliente no tiene teléfono registrado.' };
        return;
    }
    loadingTranscript.value = true;
    try {
        const { data } = await axios.get(route('agents.clients.call-transcript', [props.agent.id, client.id]), {
            params: transcriptApiParams(client),
        });
        transcriptData.value = { client, calls: data.calls || [], message: data.message };
    } catch {
        transcriptData.value = { client, calls: [], message: 'Error al cargar las llamadas' };
    } finally {
        loadingTranscript.value = false;
    }
}

// Carga cuando el panel está activo y hay cliente (una vez por cliente).
watch(
    () => (props.active && props.client ? props.client.id : null),
    (id) => {
        if (id && loadedForClientId !== id) {
            loadedForClientId = id;
            loadCalls();
        }
    },
    { immediate: true }
);

const loadCallDetail = async (callId) => {
    if (!transcriptData.value?.client || loadedCallsById.value[callId]) return;
    loadingCallDetail.value = callId;
    try {
        const { data } = await axios.get(route('agents.clients.call-transcript', [props.agent.id, transcriptData.value.client.id]), {
            params: { call_id: callId, ...transcriptApiParams(transcriptData.value.client) },
        });
        if (data.call) {
            loadedCallsById.value = { ...loadedCallsById.value, [data.call.id]: data.call };
        }
    } finally {
        loadingCallDetail.value = null;
    }
};

const selectedCallListItem = computed(() => {
    const d = transcriptData.value;
    if (!d?.calls?.length) return null;
    return d.calls[selectedCallIndex.value] ?? d.calls[0];
});

const selectedCall = computed(() => {
    const listItem = selectedCallListItem.value;
    if (!listItem) return null;
    return loadedCallsById.value[listItem.id] ?? listItem;
});

const selectedCallIsFullyLoaded = computed(() => {
    const listItem = selectedCallListItem.value;
    return listItem && loadedCallsById.value[listItem.id];
});

const effectiveAudioBase64 = computed(() => {
    const listItem = selectedCallListItem.value;
    if (!listItem?.conversation_id) return null;
    const convId = listItem.conversation_id;
    const loaded = loadedCallsById.value[listItem.id];
    if (loaded?.audio) return loaded.audio;
    return fetchedAudioByConversationId.value[convId] ?? null;
});

const fetchCallAudio = async () => {
    const listItem = selectedCallListItem.value;
    const client = transcriptData.value?.client;
    const convId = listItem?.conversation_id;
    if (!convId || !client) return;
    if (effectiveAudioBase64.value) return;
    loadingAudio.value = convId;
    try {
        const { data } = await axios.get(route('agents.clients.call-audio', [props.agent.id, client.id]), {
            params: { conversation_id: convId, ...transcriptApiParams(client) },
        });
        if (data.audio) {
            fetchedAudioByConversationId.value = { ...fetchedAudioByConversationId.value, [convId]: data.audio };
        }
    } catch (e) {
        /* silencioso: se muestra "sin audio" */
    } finally {
        loadingAudio.value = null;
    }
};

// El audio ya está local (webhook post-call): se carga solo al seleccionar la llamada.
watch(selectedCallListItem, (item) => {
    if (item?.conversation_id) fetchCallAudio();
});

const downloadCallAudio = () => {
    const listItem = selectedCallListItem.value;
    const raw = effectiveAudioBase64.value;
    if (!raw || !listItem) return;
    const url = audioSrc(raw);
    const a = document.createElement('a');
    a.href = url;
    a.download = `llamada-${listItem.id}-${(listItem.conversation_id || 'audio').slice(0, 20)}.mp3`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

const audioSrc = (audioStr) => {
    if (!audioStr) return null;
    return audioStr.startsWith('data:') ? audioStr : `data:audio/mpeg;base64,${audioStr}`;
};
</script>

<template>
    <div class="flex h-full min-h-0 overflow-hidden">
        <!-- Lista de llamadas -->
        <div
            v-if="transcriptData?.calls?.length"
            class="w-52 shrink-0 overflow-y-auto border-r border-[#e3e8ee] bg-[#f5f8fa]"
        >
            <div class="p-2">
                <p class="px-2 py-1 text-xs font-medium text-gray-500">Llamadas ({{ transcriptData.calls.length }})</p>
                <button
                    v-for="(call, idx) in transcriptData.calls"
                    :key="call.id"
                    type="button"
                    :class="[
                        'mt-1 w-full rounded-lg px-3 py-2 text-left text-sm transition',
                        selectedCallIndex === idx ? 'bg-[#1976d2] text-white' : 'bg-white text-gray-700 hover:bg-[#e3e8ee]',
                    ]"
                    @click="selectedCallIndex = idx; loadCallDetail(call.id)"
                >
                    <span class="block font-medium">
                        {{ call.created_at ? new Date(call.created_at).toLocaleDateString('es') : '—' }}
                    </span>
                    <span class="block text-xs opacity-80">
                        {{ call.created_at ? new Date(call.created_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) : '' }}
                        <span v-if="call.duration_call_seg"> · {{ Math.floor(call.duration_call_seg / 60) }}:{{ String(call.duration_call_seg % 60).padStart(2, '0') }}</span>
                    </span>
                    <span v-if="call.has_audio" class="mt-0.5 block text-xs">🔊 Audio</span>
                </button>
            </div>
        </div>

        <!-- Contenido de la llamada seleccionada -->
        <div class="min-w-0 flex-1 overflow-y-auto p-6">
            <div v-if="loadingTranscript" class="py-12 text-center text-gray-500">
                Cargando llamadas...
            </div>
            <div v-else-if="transcriptData?.message && !transcriptData?.calls?.length" class="py-8 text-center text-gray-500">
                {{ transcriptData.message }}
            </div>
            <div v-else-if="selectedCallListItem && loadingCallDetail === selectedCallListItem.id" class="py-12 text-center text-gray-500">
                Cargando transcripción y audio...
            </div>
            <div v-else-if="selectedCallListItem" class="space-y-4">
                <!-- Audio (guardado local desde el webhook post-call): carga automática -->
                <div v-if="selectedCallListItem.conversation_id" class="rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4">
                    <h4 class="mb-2 text-sm font-semibold text-gray-800">Audio de la llamada</h4>
                    <p v-if="loadingAudio === selectedCallListItem.conversation_id" class="text-sm text-gray-500">Cargando audio…</p>
                    <div v-else-if="effectiveAudioBase64" class="space-y-2">
                        <WaveformAudioPlayer :src="audioSrc(effectiveAudioBase64)" />
                        <button type="button" class="text-sm font-medium text-[#1976d2] underline hover:text-[#1565c0]" @click="downloadCallAudio">
                            Descargar audio
                        </button>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin audio para esta llamada.</p>
                </div>

                <div v-if="!selectedCallIsFullyLoaded" class="py-6 text-center text-sm text-gray-500">Cargando transcripción…</div>
                <template v-else-if="selectedCall && selectedCallIsFullyLoaded">
                    <p class="mb-4 text-xs text-gray-500">
                        Llamada del {{ selectedCall.created_at ? new Date(selectedCall.created_at).toLocaleString('es') : '—' }}
                        <span v-if="selectedCall.duration_call_seg">
                            · Duración: {{ Math.floor(selectedCall.duration_call_seg / 60) }}:{{ String(selectedCall.duration_call_seg % 60).padStart(2, '0') }}
                        </span>
                    </p>

                    <!-- Transcripción -->
                    <div v-if="(selectedCall.transcript || []).filter((i) => i.message).length" class="space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800">Transcripción</h4>
                        <div
                            v-for="(item, idx) in (selectedCall.transcript || []).filter((i) => i.message)"
                            :key="idx"
                            :class="['rounded-lg px-4 py-3', item.role === 'agent' ? 'ml-0 mr-8 border-l-4 border-[#1976d2] bg-[#e3f2fd]' : 'ml-8 mr-0 border-l-4 border-[#757575] bg-[#f5f5f5]']"
                        >
                            <span class="text-xs font-medium" :class="item.role === 'agent' ? 'text-[#1976d2]' : 'text-gray-600'">
                                {{ item.role === 'agent' ? 'Asistente' : 'Cliente' }}
                            </span>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-800">{{ item.message }}</p>
                        </div>
                    </div>

                    <!-- Variables de análisis (ElevenLabs) -->
                    <div v-if="selectedCall.variables_extraidas" class="mt-6 border-t border-[#e3e8ee] pt-6">
                        <h4 class="mb-3 text-sm font-semibold text-gray-800">Variables de análisis</h4>
                        <div class="space-y-4">
                            <div v-if="selectedCall.variables_extraidas.call_summary_title" class="rounded-lg bg-[#f5f8fa] px-3 py-2">
                                <span class="text-xs font-medium text-gray-500">Título del resumen</span>
                                <p class="mt-0.5 text-sm text-gray-800">{{ selectedCall.variables_extraidas.call_summary_title }}</p>
                            </div>
                            <div v-if="selectedCall.variables_extraidas.transcript_summary" class="rounded-lg bg-[#f5f8fa] px-3 py-2">
                                <span class="text-xs font-medium text-gray-500">Resumen de la llamada</span>
                                <p class="mt-0.5 whitespace-pre-wrap text-sm text-gray-800">{{ selectedCall.variables_extraidas.transcript_summary }}</p>
                            </div>
                            <div v-if="selectedCall.variables_extraidas.call_successful" class="rounded-lg bg-[#f5f8fa] px-3 py-2">
                                <span class="text-xs font-medium text-gray-500">Resultado</span>
                                <p class="mt-0.5 text-sm">
                                    <span :class="['inline-flex rounded px-2 py-0.5 text-xs font-medium', selectedCall.variables_extraidas.call_successful === 'success' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']">
                                        {{ selectedCall.variables_extraidas.call_successful === 'success' ? 'Éxito' : selectedCall.variables_extraidas.call_successful }}
                                    </span>
                                </p>
                            </div>
                            <div v-if="selectedCall.variables_extraidas.data_collection_results_list?.length" class="space-y-2">
                                <span class="text-xs font-medium text-gray-500">Datos recolectados</span>
                                <div class="space-y-2">
                                    <div v-for="(item, idx) in selectedCall.variables_extraidas.data_collection_results_list" :key="idx" class="rounded-lg border border-[#e3e8ee] bg-white px-3 py-2">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-sm font-medium text-[#33475b]">{{ item.data_collection_id }}</span>
                                            <span class="text-sm font-semibold text-[#1976d2]">{{ item.value }}</span>
                                        </div>
                                        <p v-if="item.rationale" class="mt-1 text-xs text-gray-500">{{ item.rationale }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="selectedCall.variables_extraidas.evaluation_criteria_results_list?.length" class="space-y-2">
                                <span class="text-xs font-medium text-gray-500">Criterios de evaluación</span>
                                <div class="space-y-2">
                                    <div v-for="(item, idx) in selectedCall.variables_extraidas.evaluation_criteria_results_list" :key="idx" class="rounded-lg border border-[#e3e8ee] bg-white px-3 py-2">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="text-sm font-medium text-[#33475b]">{{ item.criteria_id }}</span>
                                            <span :class="['inline-flex rounded px-2 py-0.5 text-xs font-medium', item.result === 'success' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800']">
                                                {{ item.result === 'success' ? 'Éxito' : item.result }}
                                            </span>
                                        </div>
                                        <p v-if="item.rationale" class="mt-1 text-xs text-gray-500">{{ item.rationale }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!selectedCall.transcript?.length && !selectedCall.variables_extraidas && !effectiveAudioBase64" class="py-8 text-center text-gray-500">
                        No hay transcripción ni variables para esta llamada.
                    </div>
                </template>
            </div>
            <div v-else class="py-12 text-center text-gray-400">Selecciona una llamada.</div>
        </div>
    </div>
</template>
