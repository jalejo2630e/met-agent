<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, computed, h, watch, onUnmounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import ConfirmCallToast from '@/Components/ConfirmCallToast.vue';
import ClientDetailModal from './Reports/ClientDetailModal.vue';
import CallAlertsModal from '../Clients/CallAlertsModal.vue';
import WaveformAudioPlayer from '@/Components/WaveformAudioPlayer.vue';
import ClientWhatsappModal from '@/Components/ClientWhatsappModal.vue';

const props = defineProps({
    agent: Object,
    clients: Object,
    filters: { type: Object, default: () => ({}) },
});

// Detalle de cliente (modal compartido con reportes)
const showClientDetail = ref(false);
const detailClientId = ref(null);
const detailClientName = ref('');
const openClientDetail = (client) => {
    detailClientId.value = client.id;
    detailClientName.value = `${client.name || ''} ${client.lastname || ''}`.trim();
    showClientDetail.value = true;
};

// Popup de alertas de llamada por cliente
const showAlertsModal = ref(false);
const alertsClient = ref(null);
const openAlerts = (client) => {
    alertsClient.value = client;
    showAlertsModal.value = true;
};
const onAlertsCountChanged = ({ clientId, count }) => {
    const c = (props.clients?.data || []).find((cl) => cl.id === clientId);
    if (c) c.alertas_llamada_count = count;
};

const page = usePage();

const transcriptApiParams = (client) => {
    const p = {};
    const campana = page.props.campana_ainoa;
    if (campana) p.campana = campana;
    if (client?.phone) p.phone = client.phone;
    return p;
};

const emit = defineEmits(['campaign-created']);

const showModal = ref(false);
const editingClient = ref(null);
const showWhatsappModal = ref(false);
const selectedClient = ref(null);
const loadingCall = ref(null);
const selectedIds = ref(new Set());
const showTranscriptModal = ref(false);
const transcriptData = ref(null);
const loadingTranscript = ref(null);
const selectedCallIndex = ref(0);
const loadedCallsById = ref({});
const loadingCallDetail = ref(null);
const fetchedAudioByConversationId = ref({});
const loadingAudio = ref(null);

const selectedClients = computed(() => {
    const ids = selectedIds.value;
    return (props.clients?.data || []).filter(c => ids.has(c.id));
});

const selectedWithPhone = computed(() => selectedClients.value.filter(c => c.phone));

const toggleSelect = (client) => {
    const next = new Set(selectedIds.value);
    if (next.has(client.id)) next.delete(client.id);
    else next.add(client.id);
    selectedIds.value = next;
};

const toggleSelectAll = () => {
    const all = props.clients?.data || [];
    const allIds = new Set(all.map(c => c.id));
    if (selectedIds.value.size >= allIds.size) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(allIds);
    }
};

const isAllSelected = computed(() => {
    const all = props.clients?.data || [];
    return all.length > 0 && all.every((c) => selectedIds.value.has(c.id));
});

const form = useForm({
    name: '',
    lastname: '',
    email: '',
    phone: '',
    document_type: '',
    document: '',
    custom_fields: {},
});

const dynamicFields = computed(() => props.agent?.client_fields || props.agent?.clientFields || []);

const statusOptions = [
    { value: 'no_contactado', label: 'No contactado' },
    { value: 'llamada_programada', label: 'Llamada programada' },
    { value: 'contactado', label: 'Contactado' },
    { value: 'en_proceso', label: 'En proceso' },
    { value: 'no_interesado', label: 'No interesado' },
    { value: 'otro', label: 'Otro (escribir)' },
];

const documentTypes = [
    { value: '', label: 'Seleccionar...' },
    { value: 'CC', label: 'Cédula de ciudadanía (CC)' },
    { value: 'CE', label: 'Cédula de extranjería (CE)' },
    { value: 'NIT', label: 'NIT' },
    { value: 'Pasaporte', label: 'Pasaporte' },
    { value: 'Otro', label: 'Otro' },
];

const resetNewClientForm = () => {
    form.reset();
    const defaults = {};
    dynamicFields.value.forEach((f) => {
        defaults[f.field_name] = '';
    });
    form.custom_fields = defaults;
    form.phone = '';
};

const openCreate = () => {
    editingClient.value = null;
    resetNewClientForm();
    showModal.value = true;
};

const openEdit = (client) => {
    editingClient.value = client;
    form.name = client.name;
    form.lastname = client.lastname;
    form.email = client.email;
    form.phone = client.phone || '';
    form.document_type = client.document_type || '';
    form.document = client.document || '';
    form.custom_fields = { ...client.custom_fields };
    showModal.value = true;
};

const submitClient = () => {
    const onSaved = () => {
        showModal.value = false;
        editingClient.value = null;
        resetNewClientForm();
    };

    if (editingClient.value) {
        form.put(route('agents.clients.update', [props.agent, editingClient.value]), {
            onSuccess: onSaved,
        });
    } else {
        form.post(route('agents.clients.store', props.agent), {
            onSuccess: onSaved,
        });
    }
};

const deleteClient = (client) => {
    if (confirm('¿Eliminar este cliente?')) {
        router.delete(route('agents.clients.destroy', [props.agent, client]));
    }
};

const clientCallbackRequests = ref([]);
const loadingCallbacks = ref(false);

const openWhatsapp = async (client) => {
    selectedClient.value = client;
    showWhatsappModal.value = true;
    clientCallbackRequests.value = [];
    loadingCallbacks.value = true;
    try {
        const { data } = await axios.get(route('agents.callback-requests.index', props.agent), {
            params: { client_id: client.id },
        });
        clientCallbackRequests.value = data.callback_requests || [];
    } catch {
        clientCallbackRequests.value = [];
    } finally {
        loadingCallbacks.value = false;
    }
};

const plantillas = computed(() => {
    const list = props.agent?.message_config?.plantillas || [];
    return Array.isArray(list) ? list.filter((p) => p?.id?.trim()) : [];
});

const defaultPlantillaId = computed(() => props.agent?.message_config?.default_plantilla_id || '');

const hasCallWebhook = () => props.agent?.call_config?.webhook_url;
const hasWhatsappWebhook = () => props.agent?.message_config?.webhook_url;

function getScheduleTimes(sc) {
    const slots = sc?.campaign_slots;
    if (Array.isArray(slots) && slots.length > 0) {
        return slots.map((s) => s?.time).filter(Boolean);
    }
    return sc?.campaign_times ?? [];
}

const hasCallSchedule = () => {
    const sc = props.agent?.call_config?.schedule_config;
    const times = getScheduleTimes(sc);
    const days = sc?.days_of_week;
    return Array.isArray(times) && times.length > 0 && Array.isArray(days) && days.length > 0;
};

const hasWhatsappSchedule = () => {
    const sc = props.agent?.message_config?.schedule_config;
    const times = getScheduleTimes(sc);
    const days = sc?.days_of_week;
    return Array.isArray(times) && times.length > 0 && Array.isArray(days) && days.length > 0;
};

const showBulkActionModal = ref(false);
const bulkActionType = ref(null); // 'call' | 'whatsapp'
const bulkActionLoading = ref(false);
const bulkPlantillaId = ref('');
const bulkScheduledDate = ref('');
const bulkScheduledTime = ref('');
const showBulkScheduleForm = ref(false);

const openBulkActionModal = (type) => {
    const withPhone = selectedWithPhone.value;
    if (!withPhone.length) {
        toast.error('Selecciona al menos un cliente con teléfono.');
        return;
    }
    if (type === 'call' && !hasCallWebhook()) {
        toast.error('No hay webhook de llamadas configurado.');
        return;
    }
    if (type === 'whatsapp' && !hasWhatsappWebhook()) {
        toast.error('No hay webhook de mensajes configurado.');
        return;
    }
    bulkActionType.value = type;
    bulkPlantillaId.value = type === 'whatsapp' ? (props.agent?.message_config?.default_plantilla_id || '') : '';
    bulkScheduledDate.value = '';
    bulkScheduledTime.value = '';
    showBulkScheduleForm.value = false;
    showBulkActionModal.value = true;
};

const canSchedule = () => {
    if (bulkActionType.value === 'call') return hasCallSchedule();
    if (bulkActionType.value === 'whatsapp') return hasWhatsappSchedule();
    return false;
};

const submitBulkAction = async (immediate, scheduledDate = null, scheduledTime = null) => {
    const type = bulkActionType.value;
    const clientIds = selectedWithPhone.value.map(c => c.id);
    bulkActionLoading.value = true;
    try {
        const payload = { type, client_ids: clientIds, immediate };
        if (type === 'whatsapp' && immediate && bulkPlantillaId.value) {
            payload.id_plantilla = bulkPlantillaId.value;
        }
        if (scheduledDate) payload.scheduled_date = scheduledDate;
        if (scheduledTime) payload.scheduled_time = scheduledTime;
        const { data } = await axios.post(route('agents.contact-queues.store', props.agent), payload);
        if (data.success) {
            toast.success(data.message);
            selectedIds.value = new Set();
            showBulkActionModal.value = false;
            showBulkScheduleForm.value = false;
            emit('campaign-created');
        } else {
            toast.error(data.message || 'Error al crear la cola.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al crear la cola.');
    } finally {
        bulkActionLoading.value = false;
    }
};

const submitBulkScheduled = () => {
    if (!bulkScheduledDate.value) {
        toast.error('Selecciona la fecha.');
        return;
    }
    const timeStr = bulkScheduledTime.value && /^\d{2}:\d{2}/.test(bulkScheduledTime.value)
        ? bulkScheduledTime.value.substring(0, 5)
        : '08:00';
    submitBulkAction(false, bulkScheduledDate.value, timeStr);
};

const doCall = async (client) => {
    loadingCall.value = client.id;
    try {
        const { data } = await axios.post(route('agents.clients.initiate-call', [props.agent, client]));
        if (data.success) {
            toast.success(data.message || 'Llamada iniciada correctamente.');
        } else {
            toast.error(data.message || 'Error al iniciar la llamada.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al iniciar la llamada.');
    } finally {
        loadingCall.value = null;
    }
};

const initiateCall = (client) => {
    if (!hasCallWebhook()) {
        toast.error('No hay webhook de llamadas configurado para esta empresa.');
        return;
    }
    toast(
        (props) => h(ConfirmCallToast, { ...props }),
        {
            closeOnClick: false,
            autoClose: false,
            type: 'default',
            data: {
                message: '¿Realmente deseas iniciar la llamada a este cliente?',
                onConfirm: () => doCall(client),
                onCancel: () => {},
            },
        }
    );
};

const initiateBulkWhatsapp = () => openBulkActionModal('whatsapp');
const initiateBulkCalls = () => openBulkActionModal('call');

const openTranscript = async (client) => {
    if (!client.phone) return;
    loadingTranscript.value = client.id;
    transcriptData.value = null;
    selectedCallIndex.value = 0;
    loadedCallsById.value = {};
    loadingCallDetail.value = null;
    fetchedAudioByConversationId.value = {};
    loadingAudio.value = null;
    showTranscriptModal.value = true;
    try {
        const { data } = await axios.get(route('agents.clients.call-transcript', [props.agent, client]), {
            params: transcriptApiParams(client),
        });
        transcriptData.value = {
            client: client,
            calls: data.calls || [],
            message: data.message,
        };
    } catch {
        transcriptData.value = { client, calls: [], message: 'Error al cargar las llamadas' };
    } finally {
        loadingTranscript.value = null;
    }
};

const loadCallDetail = async (callId) => {
    if (!transcriptData.value?.client || loadedCallsById.value[callId]) return;
    loadingCallDetail.value = callId;
    try {
        const { data } = await axios.get(route('agents.clients.call-transcript', [props.agent, transcriptData.value.client]), {
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
        const { data } = await axios.get(route('agents.clients.call-audio', [props.agent, client]), {
            params: { conversation_id: convId, ...transcriptApiParams(client) },
        });
        if (data.audio) {
            fetchedAudioByConversationId.value = {
                ...fetchedAudioByConversationId.value,
                [convId]: data.audio,
            };
        } else {
            toast.error(data.message || 'No hay audio para esta conversación');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al cargar el audio');
    } finally {
        loadingAudio.value = null;
    }
};

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
    if (audioStr.startsWith('data:')) return audioStr;
    return `data:audio/mpeg;base64,${audioStr}`;
};

// El audio ya está guardado local (webhook post-call de ElevenLabs): se carga
// solo al seleccionar la llamada, sin pulsar "reproducir".
watch(selectedCallListItem, (item) => {
    if (item?.conversation_id) fetchCallAudio();
});

const filterDate = ref(props.filters?.date ?? '');
const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? '');

let searchDebounceTimer = null;
const applyTableFilters = () => {
    router.get(route('agents.show', props.agent), {
        date: filterDate.value || undefined,
        search: searchInput.value.trim() || undefined,
        status: statusFilter.value || undefined,
        page: 1,
    }, { preserveScroll: true });
};

const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applyTableFilters(), 400);
};

onUnmounted(() => clearTimeout(searchDebounceTimer));

const clearAllTableFilters = () => {
    filterDate.value = '';
    searchInput.value = '';
    statusFilter.value = '';
    applyTableFilters();
};

watch(() => props.filters, (f) => {
    filterDate.value = f?.date ?? '';
    searchInput.value = f?.search ?? '';
    statusFilter.value = f?.status ?? '';
}, { deep: true });

const applyDateFilter = () => applyTableFilters();

const goToPage = (page) => {
    if (page < 1 || page > props.clients.last_page) return;
    router.get(route('agents.show', props.agent), {
        date: filterDate.value || undefined,
        search: searchInput.value.trim() || undefined,
        status: statusFilter.value || undefined,
        page,
    }, { preserveScroll: true });
};

const exportQueryString = computed(() => {
    const p = new URLSearchParams();
    if (filterDate.value) p.set('date', filterDate.value);
    const s = searchInput.value.trim();
    if (s) p.set('search', s);
    if (statusFilter.value) p.set('status', statusFilter.value);
    const qs = p.toString();
    return qs ? `?${qs}` : '';
});

const formatLoadedAt = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es', { dateStyle: 'short' }) + ' ' + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Lista de clientes -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Lista de clientes</h3>
                        <p class="mt-0.5 text-sm text-gray-500">
                            Filtros en servidor · {{ clients.per_page ?? 20 }} por página
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            :href="route('agents.clients.export', agent) + exportQueryString"
                            class="inline-flex items-center gap-1.5 rounded-md border border-[#e3e8ee] bg-white px-3 py-2 text-sm text-[#33475b] hover:bg-[#f5f8fa]"
                            download
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Descargar Excel
                        </a>
                    </div>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                        <template v-if="selectedIds.size > 0">
                            <button
                                v-if="hasCallWebhook()"
                                type="button"
                                class="rounded-md border border-[#1976d2] bg-white px-3 py-1.5 text-sm text-[#1976d2] hover:bg-[#1976d2]/5 disabled:opacity-50"
                                :disabled="selectedWithPhone.length === 0"
                                @click="initiateBulkCalls"
                            >
                                Llamadas masivas ({{ selectedWithPhone.length }})
                            </button>
                            <button
                                v-if="hasWhatsappWebhook()"
                                type="button"
                                class="rounded-md border border-[#25D366] bg-white px-3 py-1.5 text-sm text-[#25D366] hover:bg-[#25D366]/5 disabled:opacity-50"
                                :disabled="selectedWithPhone.length === 0"
                                @click="initiateBulkWhatsapp"
                            >
                                Mensajes masivos WhatsApp ({{ selectedWithPhone.length }})
                            </button>
                            <button
                                type="button"
                                class="text-sm text-gray-500 hover:text-gray-700"
                                @click="selectedIds = new Set()"
                            >
                                Deseleccionar
                            </button>
                        </template>
                        <PrimaryButton @click="openCreate">Nuevo cliente</PrimaryButton>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50/90 p-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                        <div class="lg:col-span-4">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Buscar</label>
                            <input
                                v-model="searchInput"
                                type="search"
                                autocomplete="off"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                placeholder="Nombre, apellido, email, teléfono, documento…"
                                @input="onSearchInput"
                            />
                        </div>
                        <div class="lg:col-span-3">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Estado</label>
                            <select
                                v-model="statusFilter"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                @change="applyTableFilters"
                            >
                                <option value="">Todos los estados</option>
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div class="lg:col-span-3">
                            <label class="mb-1 block text-xs font-medium text-gray-600">Fecha (carga o registro)</label>
                            <input
                                v-model="filterDate"
                                type="date"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2 lg:col-span-2">
                            <button
                                type="button"
                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-50"
                                @click="applyDateFilter"
                            >
                                Aplicar fecha
                            </button>
                            <button
                                v-if="filterDate || searchInput.trim() || statusFilter"
                                type="button"
                                class="rounded-md px-3 py-2 text-sm text-gray-600 hover:bg-gray-100"
                                @click="clearAllTableFilters"
                            >
                                Limpiar filtros
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                    <div class="max-h-[min(70vh,880px)] overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-100 shadow-sm">
                            <tr>
                                <th class="w-10 px-3 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        class="rounded border-gray-300"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Nombre</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Email</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Teléfono</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Documento</th>
                                <th v-for="f in dynamicFields" :key="f.id" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ f.field_name }}</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Contactos</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Llamadas</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Cargas</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Fecha carga</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Alertas de llamada</th>
                                <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="(client, idx) in clients.data"
                                :key="client.id"
                                class="transition-colors hover:bg-[#f0f4f8]"
                                :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'"
                            >
                                <td class="w-10 px-3 py-2.5">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.has(client.id)"
                                        class="rounded border-gray-300"
                                        @change="toggleSelect(client)"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-medium">
                                    <button type="button" class="text-left text-[#1976d2] hover:underline" @click="openClientDetail(client)">{{ client.name }} {{ client.lastname }}</button>
                                </td>
                                <td class="max-w-[180px] truncate px-3 py-2.5 text-gray-700" :title="client.email">{{ client.email }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center gap-1">
                                        {{ client.phone || '-' }}
                                        <button
                                            v-if="client.phone"
                                            type="button"
                                            class="inline-flex rounded p-1 text-[#1976d2] hover:bg-[#1976d2]/10 transition"
                                            title="Ver transcripción de llamada"
                                            :disabled="loadingTranscript === client.id"
                                            @click="openTranscript(client)"
                                        >
                                            <svg v-if="loadingTranscript === client.id" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </button>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-gray-700">{{ client.document_type }} {{ client.document }}</td>
                                <td v-for="f in dynamicFields" :key="f.id" class="max-w-[120px] truncate px-3 py-2.5 text-sm text-gray-600">{{ (client.custom_fields || {})[f.field_name] ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm text-gray-600">{{ client.contact_logs_count ?? 0 }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm text-gray-600">{{ client.calls_count ?? 0 }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm text-gray-600">{{ client.load_dates_count ?? 0 }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm text-gray-500">{{ formatLoadedAt(client.loaded_at) }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
                                        :class="(client.alertas_llamada_count ?? 0) > 0
                                            ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                            : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100'"
                                        :title="`Ver alertas de llamada de ${client.name} ${client.lastname || ''}`"
                                        @click="openAlerts(client)"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                        {{ client.alertas_llamada_count ?? 0 }}
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right">
                                    <button
                                        v-if="hasCallWebhook()"
                                        type="button"
                                        class="inline-flex items-center justify-center rounded p-1.5 text-[#1976d2] hover:bg-[#1976d2]/10 transition disabled:opacity-50"
                                        title="Llamar"
                                        :disabled="loadingCall === client.id"
                                        @click="initiateCall(client)"
                                    >
                                        <svg v-if="loadingCall === client.id" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                        </svg>
                                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded p-1.5 text-[#25D366] hover:bg-[#25D366]/10 transition"
                                        title="WhatsApp"
                                        @click="openWhatsapp(client)"
                                    >
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    </button>
                                    <button class="ml-2 text-indigo-600 hover:text-indigo-900" @click="openEdit(client)">
                                        Editar
                                    </button>
                                    <button
                                        v-if="$page.props.auth.canDeleteAgents"
                                        class="ml-2 text-red-600 hover:text-red-900"
                                        @click="deleteClient(client)"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!clients.data?.length">
                                <td :colspan="12 + dynamicFields.length" class="px-4 py-10 text-center text-gray-500">Sin clientes con los filtros actuales.</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
                <div
                    v-if="clients.total > 0"
                    class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 pt-4"
                >
                    <p class="text-sm text-gray-600">
                        Mostrando <span class="font-medium text-gray-800">{{ clients.from ?? 0 }}</span> a
                        <span class="font-medium text-gray-800">{{ clients.to ?? 0 }}</span> de
                        <span class="font-medium text-gray-800">{{ clients.total }}</span> clientes
                        <span class="text-gray-400">· {{ clients.per_page ?? 20 }} por página</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="clients.current_page <= 1"
                            @click="goToPage(clients.current_page - 1)"
                        >
                            Anterior
                        </button>
                        <span class="text-sm text-gray-600">
                            Página {{ clients.current_page }} de {{ clients.last_page }}
                        </span>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="clients.current_page >= clients.last_page"
                            @click="goToPage(clients.current_page + 1)"
                        >
                            Siguiente
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal crear/editar cliente -->
        <div
            v-show="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            @keydown.esc="showModal = false"
        >
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showModal = false" />
                <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-medium">
                        {{ editingClient ? 'Editar cliente' : 'Nuevo cliente' }}
                    </h3>
                    <form @submit.prevent="submitClient" class="mt-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Nombre" />
                                <TextInput v-model="form.name" class="mt-1 w-full" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div>
                                <InputLabel value="Apellido" />
                                <TextInput v-model="form.lastname" class="mt-1 w-full" required />
                                <InputError :message="form.errors.lastname" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Email" />
                            <TextInput v-model="form.email" type="email" class="mt-1 w-full" required />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div>
                            <InputLabel value="Teléfono" />
                            <TextInput v-model="form.phone" type="text" class="mt-1 w-full" placeholder="+57 300 123 4567" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Tipo documento" />
                                <select
                                    v-model="form.document_type"
                                    class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                >
                                    <option v-for="opt in documentTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Documento" />
                                <TextInput v-model="form.document" class="mt-1 w-full" />
                            </div>
                        </div>
                        <div v-if="dynamicFields.length" class="space-y-2">
                            <h4 class="text-sm font-medium">Campos adicionales</h4>
                            <div
                                v-for="f in dynamicFields"
                                :key="f.id"
                                class="flex flex-col"
                            >
                                <InputLabel :value="f.field_name + (f.required ? ' *' : '')" />
                                <TextInput
                                    v-model="form.custom_fields[f.field_name]"
                                    :type="f.field_type === 'number' ? 'number' : f.field_type === 'date' ? 'date' : 'text'"
                                    class="mt-1 w-full"
                                    :required="f.required"
                                />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" class="rounded border px-4 py-2" @click="showModal = false">
                                Cancelar
                            </button>
                            <PrimaryButton :disabled="form.processing">
                                {{ editingClient ? 'Guardar' : 'Crear' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal acción masiva (llamadas / WhatsApp) -->
        <div
            v-show="showBulkActionModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            @keydown.esc="showBulkActionModal = false"
        >
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showBulkActionModal = false" />
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ bulkActionType === 'call' ? 'Llamadas masivas' : 'Mensajes masivos WhatsApp' }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ selectedWithPhone.length }} cliente(s) con teléfono seleccionado(s).
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        ¿Cómo deseas ejecutar el contacto?
                    </p>
                    <div v-if="bulkActionType === 'whatsapp' && plantillas.length" class="mt-3 rounded border border-[#e3e8ee] bg-gray-50 p-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Plantilla (para ejecutar ahora)</label>
                        <select v-model="bulkPlantillaId" class="w-full rounded-md border-[#e3e8ee] text-sm">
                            <option value="">— Plantilla por defecto —</option>
                            <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.name || p.id }}</option>
                        </select>
                    </div>
                    <div class="mt-4 flex flex-col gap-2">
                        <button
                            type="button"
                            :class="[
                                bulkActionType === 'call'
                                    ? 'rounded-md border border-[#1976d2] bg-[#1976d2] px-4 py-2 text-sm text-white hover:bg-[#1565c0]'
                                    : 'rounded-md border border-[#25D366] bg-[#25D366] px-4 py-2 text-sm text-white hover:bg-[#20BD5A]',
                            ]"
                            :disabled="bulkActionLoading"
                            @click="submitBulkAction(true)"
                        >
                            {{ bulkActionLoading ? 'Procesando...' : 'Ejecutar ahora (colas en segundo plano)' }}
                        </button>
                        <button
                            v-if="canSchedule()"
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="bulkActionLoading"
                            @click="submitBulkAction(false)"
                        >
                            Programar según horarios configurados
                        </button>
                        <p v-else class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Configura horarios de campaña en la pestaña {{ bulkActionType === 'call' ? 'Llamadas' : 'Mensajes' }} para programar.
                        </p>
                        <div class="mt-2">
                            <button
                                type="button"
                                class="w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="bulkActionLoading"
                                @click="showBulkScheduleForm = !showBulkScheduleForm"
                            >
                                {{ showBulkScheduleForm ? 'Ocultar' : 'Programar en fecha y hora específica' }}
                            </button>
                            <div v-if="showBulkScheduleForm" class="mt-3 space-y-2 rounded border border-[#e3e8ee] bg-gray-50 p-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Fecha *</label>
                                    <input
                                        v-model="bulkScheduledDate"
                                        type="date"
                                        :min="new Date().toISOString().split('T')[0]"
                                        class="w-full rounded-md border-[#e3e8ee] text-sm"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Hora (opcional)</label>
                                    <input
                                        v-model="bulkScheduledTime"
                                        type="time"
                                        class="w-full rounded-md border-[#e3e8ee] text-sm"
                                    />
                                </div>
                                <button
                                    type="button"
                                    :class="[
                                        bulkActionType === 'call'
                                            ? 'rounded-md border border-[#1976d2] bg-[#1976d2] px-3 py-1.5 text-sm text-white hover:bg-[#1565c0]'
                                            : 'rounded-md border border-[#25D366] bg-[#25D366] px-3 py-1.5 text-sm text-white hover:bg-[#20BD5A]',
                                    ]"
                                    :disabled="bulkActionLoading || !bulkScheduledDate"
                                    @click="submitBulkScheduled"
                                >
                                    Programar
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
                            :disabled="bulkActionLoading"
                            @click="showBulkActionModal = false"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal WhatsApp (componente compartido con el listado de clientes) -->
        <ClientWhatsappModal
            :show="showWhatsappModal"
            :agent="agent"
            :client="selectedClient"
            :plantillas="plantillas"
            :default-plantilla-id="defaultPlantillaId"
            @close="showWhatsappModal = false"
        >
            <div class="border-t pt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Solicitudes de callback (registro en cliente)</h4>
                <div v-if="loadingCallbacks" class="text-sm text-gray-500">Cargando...</div>
                <div v-else-if="!clientCallbackRequests.length" class="text-sm text-gray-500">No hay solicitudes de callback para este cliente.</div>
                <div v-else class="max-h-32 overflow-y-auto space-y-1 rounded border border-gray-200 bg-gray-50 p-2 text-xs">
                    <div
                        v-for="cb in clientCallbackRequests"
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
        </ClientWhatsappModal>

        <!-- Modal transcripción de llamada -->
        <div
            v-show="showTranscriptModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            @keydown.esc="showTranscriptModal = false"
        >
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showTranscriptModal = false" />
                <div class="relative max-h-[85vh] w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-[#e3e8ee] px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Transcripción de llamada
                            <span v-if="transcriptData?.client" class="text-sm font-normal text-gray-500">
                                — {{ transcriptData.client.name }} {{ transcriptData.client.lastname }}
                            </span>
                        </h3>
                        <button
                            type="button"
                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            @click="showTranscriptModal = false"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex max-h-[calc(85vh-80px)] overflow-hidden">
                        <!-- Lista de llamadas -->
                        <div
                            v-if="transcriptData?.calls?.length"
                            class="w-56 shrink-0 border-r border-[#e3e8ee] overflow-y-auto bg-[#f5f8fa]"
                        >
                            <div class="p-2">
                                <p class="px-2 py-1 text-xs font-medium text-gray-500">Llamadas ({{ transcriptData.calls.length }})</p>
                                <button
                                    v-for="(call, idx) in transcriptData.calls"
                                    :key="call.id"
                                    type="button"
                                    :class="[
                                        'mt-1 w-full rounded-lg px-3 py-2 text-left text-sm transition',
                                        selectedCallIndex === idx
                                            ? 'bg-[#1976d2] text-white'
                                            : 'bg-white text-gray-700 hover:bg-[#e3e8ee]'
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
                                Cargando fechas de llamadas...
                            </div>
                            <div
                                v-else-if="transcriptData?.message && !transcriptData?.calls?.length"
                                class="py-8 text-center text-gray-500"
                            >
                                {{ transcriptData.message }}
                            </div>
                            <div v-else-if="selectedCallListItem && loadingCallDetail === selectedCallListItem.id" class="py-12 text-center text-gray-500">
                                Cargando transcripción y audio...
                            </div>
                            <div v-else-if="selectedCallListItem" class="space-y-4">
                                <div
                                    v-if="selectedCallListItem.conversation_id"
                                    class="rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4"
                                >
                                    <h4 class="mb-2 text-sm font-semibold text-gray-800">Audio de la llamada</h4>
                                    <p v-if="loadingAudio === selectedCallListItem.conversation_id" class="text-sm text-gray-500">
                                        Cargando audio…
                                    </p>
                                    <div v-else-if="effectiveAudioBase64" class="space-y-2">
                                        <WaveformAudioPlayer :src="audioSrc(effectiveAudioBase64)" />
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-[#1976d2] underline hover:text-[#1565c0]"
                                            @click="downloadCallAudio"
                                        >
                                            Descargar audio
                                        </button>
                                    </div>
                                    <p v-else class="text-sm text-gray-400">Sin audio para esta llamada.</p>
                                </div>

                                <div v-if="!selectedCallIsFullyLoaded" class="py-6 text-center text-sm text-gray-500">
                                    Cargando transcripción…
                                </div>
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
                                        :class="[
                                            'rounded-lg px-4 py-3',
                                            item.role === 'agent'
                                                ? 'ml-0 mr-8 bg-[#e3f2fd] border-l-4 border-[#1976d2]'
                                                : 'ml-8 mr-0 bg-[#f5f5f5] border-l-4 border-[#757575]'
                                        ]"
                                    >
                                        <span class="text-xs font-medium" :class="item.role === 'agent' ? 'text-[#1976d2]' : 'text-gray-600'">
                                            {{ item.role === 'agent' ? 'Asistente' : 'Cliente' }}
                                        </span>
                                        <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-800">{{ item.message }}</p>
                                    </div>
                                </div>

                                <!-- Variables extraídas -->
                                <div v-if="selectedCall.variables_extraidas" class="mt-6 border-t border-[#e3e8ee] pt-6">
                                    <h4 class="text-sm font-semibold text-gray-800 mb-3">Variables extraídas</h4>
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
                                                <span
                                                    :class="[
                                                        'inline-flex rounded px-2 py-0.5 text-xs font-medium',
                                                        selectedCall.variables_extraidas.call_successful === 'success'
                                                            ? 'bg-green-100 text-green-800'
                                                            : 'bg-amber-100 text-amber-800'
                                                    ]"
                                                >
                                                    {{ selectedCall.variables_extraidas.call_successful === 'success' ? 'Éxito' : selectedCall.variables_extraidas.call_successful }}
                                                </span>
                                            </p>
                                        </div>
                                        <div v-if="selectedCall.variables_extraidas.data_collection_results_list?.length" class="space-y-2">
                                            <span class="text-xs font-medium text-gray-500">Datos recolectados</span>
                                            <div class="space-y-2">
                                                <div
                                                    v-for="(item, idx) in selectedCall.variables_extraidas.data_collection_results_list"
                                                    :key="idx"
                                                    class="rounded-lg border border-[#e3e8ee] bg-white px-3 py-2"
                                                >
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
                                                <div
                                                    v-for="(item, idx) in selectedCall.variables_extraidas.evaluation_criteria_results_list"
                                                    :key="idx"
                                                    class="rounded-lg border border-[#e3e8ee] bg-white px-3 py-2"
                                                >
                                                    <div class="flex items-baseline justify-between gap-2">
                                                        <span class="text-sm font-medium text-[#33475b]">{{ item.criteria_id }}</span>
                                                        <span
                                                            :class="[
                                                                'inline-flex rounded px-2 py-0.5 text-xs font-medium',
                                                                item.result === 'success' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'
                                                            ]"
                                                        >
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ClientDetailModal
            :show="showClientDetail"
            :agent="agent"
            :client-id="detailClientId"
            :client-name="detailClientName"
            @close="showClientDetail = false"
        />

        <CallAlertsModal
            :show="showAlertsModal"
            :agent="agent"
            :client="alertsClient"
            @close="showAlertsModal = false"
            @count-changed="onAlertsCountChanged"
        />
    </div>
</template>
