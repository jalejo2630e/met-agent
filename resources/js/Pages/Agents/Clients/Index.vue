<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, computed, h, watch, onUnmounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import axios from 'axios';
import ConfirmCallToast from '@/Components/ConfirmCallToast.vue';
import CallAlertsModal from './CallAlertsModal.vue';
import ClientContactModal from '@/Components/ClientContactModal.vue';

const props = defineProps({
    agent: Object,
    clients: Object,
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();

const showModal = ref(false);
const editingClient = ref(null);
const showAlertsModal = ref(false);
const alertsClient = ref(null);

function openAlerts(client) {
    alertsClient.value = client;
    showAlertsModal.value = true;
}

function onAlertsCountChanged({ clientId, count }) {
    const c = props.clients?.data?.find((cl) => cl.id === clientId);
    if (c) c.alertas_llamada_count = count;
}
const loadingCall = ref(null);

// Modal único de contacto (pestañas Llamadas / WhatsApp)
const showContactModal = ref(false);
const contactClient = ref(null);
const contactInitialTab = ref('calls');

const openContact = (client, tab = 'calls') => {
    contactClient.value = client;
    contactInitialTab.value = tab;
    showContactModal.value = true;
};

const form = useForm({
    name: '',
    lastname: '',
    email: '',
    phone: '',
    document_type: '',
    document: '',
    status: 'no_contactado',
    status_custom: '',
    custom_fields: {},
});

const statusOptions = [
    { value: 'no_contactado', label: 'No contactado' },
    { value: 'llamada_programada', label: 'Llamada programada' },
    { value: 'contactado', label: 'Contactado' },
    { value: 'en_proceso', label: 'En proceso' },
    { value: 'no_interesado', label: 'No interesado' },
    { value: 'otro', label: 'Otro (escribir)' },
];

const statusLabels = {
    no_contactado: 'No contactado',
    llamada_programada: 'Llamada programada',
    contactado: 'Contactado',
    en_proceso: 'En proceso',
    no_interesado: 'No interesado',
};

const getStatusLabel = (status) => {
    if (!status) return '—';
    return statusLabels[status] || status;
};

const getStatusClass = (status) => {
    if (status === 'llamada_programada') return 'bg-amber-100 text-amber-800';
    if (status === 'no_contactado') return 'bg-gray-100 text-gray-600';
    if (status === 'contactado') return 'bg-green-100 text-green-800';
    if (status === 'en_proceso') return 'bg-blue-100 text-blue-800';
    if (status === 'no_interesado') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-600';
};

const dynamicFields = computed(() => props.agent?.client_fields || props.agent?.clientFields || []);

// Variables que el agente extrae de las conversaciones de texto (WhatsApp/SMS).
// Se muestran como columnas y se llenan cruzando por teléfono (ver ConversationExtractionService).
const extractionVars = computed(() => props.agent?.extraction_variables || props.agent?.extractionVariables || []);

const fieldForm = useForm({
    field_name: '',
    field_type: 'string',
    required: false,
});

const fieldTypes = [
    { value: 'string', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'boolean', label: 'Booleano' },
    { value: 'date', label: 'Fecha' },
    { value: 'text', label: 'Texto largo' },
];

const documentTypes = [
    { value: '', label: 'Seleccionar...' },
    { value: 'CC', label: 'Cédula de ciudadanía (CC)' },
    { value: 'CE', label: 'Cédula de extranjería (CE)' },
    { value: 'NIT', label: 'NIT' },
    { value: 'Pasaporte', label: 'Pasaporte' },
    { value: 'Otro', label: 'Otro' },
];

/** Vacía el formulario de cliente (mismo estado que al abrir «Nuevo cliente»). */
const resetNewClientForm = () => {
    form.reset();
    const defaults = {};
    dynamicFields.value.forEach((f) => {
        defaults[f.field_name] = '';
    });
    form.custom_fields = defaults;
    form.phone = '';
    form.status = 'no_contactado';
    form.status_custom = '';
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
    const isKnownStatus = statusOptions.some(o => o.value === client.status);
    form.status = isKnownStatus ? client.status : 'otro';
    form.status_custom = isKnownStatus ? '' : (client.status || '');
    form.custom_fields = { ...client.custom_fields };
    showModal.value = true;
};

const plantillas = computed(() => {
    const list = props.agent?.message_config?.plantillas || [];
    return Array.isArray(list) ? list.filter((p) => p?.id?.trim()) : [];
});

const defaultPlantillaId = computed(() => props.agent?.message_config?.default_plantilla_id || '');

const hasCallWebhook = () => props.agent?.call_config?.webhook_url;

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

const getStatusToSubmit = () => {
    if (form.status === 'otro' && form.status_custom?.trim()) {
        return form.status_custom.trim();
    }
    if (form.status && form.status !== 'otro') {
        return form.status;
    }
    return 'no_contactado';
};

const submitClient = () => {
    const transform = (data) => {
        const { status_custom, ...rest } = data;
        return { ...rest, status: getStatusToSubmit() };
    };
    const onSaved = () => {
        showModal.value = false;
        editingClient.value = null;
        resetNewClientForm();
    };

    if (editingClient.value) {
        form.transform(transform).put(route('agents.clients.update', [props.agent, editingClient.value]), {
            onSuccess: onSaved,
        });
    } else {
        form.transform(transform).post(route('agents.clients.store', props.agent), {
            onSuccess: onSaved,
        });
    }
};

const deleteClient = (client) => {
    if (confirm('¿Eliminar este cliente?')) {
        router.delete(route('agents.clients.destroy', [props.agent, client]));
    }
};

const submitField = () => {
    fieldForm.post(route('agents.client-fields.store', props.agent), {
        onSuccess: () => fieldForm.reset(),
    });
};

const deleteField = (field) => {
    if (confirm('¿Eliminar este campo?')) {
        router.delete(route('agents.client-fields.destroy', [props.agent, field]));
    }
};

const filterDate = ref(props.filters?.date ?? '');
/** Búsqueda y filtros en servidor (DataTable) */
const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? '');

let searchDebounceTimer = null;
const applyTableFilters = () => {
    router.get(route('agents.clients.index', props.agent), {
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
    router.get(route('agents.clients.index', props.agent), {
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
</script>

<template>
    <Head :title="`Clientes - ${agent.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Link :href="route('agents.show', agent)" class="text-gray-400 hover:text-gray-600">←</Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Clientes - {{ agent.name }}
                    </h2>
                </div>
                <PrimaryButton @click="openCreate">Nuevo cliente</PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-[92rem] space-y-6 sm:px-6 lg:px-8">
                <!-- Campos dinámicos -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900">Campos dinámicos del cliente</h3>
                        <p class="mt-1 text-sm text-gray-500">Agrega campos adicionales para los clientes de esta empresa.</p>
                        <form @submit.prevent="submitField" class="mt-4 flex flex-wrap gap-4">
                            <div>
                                <InputLabel value="Nombre del campo" />
                                <TextInput v-model="fieldForm.field_name" class="mt-1" placeholder="ej: teléfono" required />
                                <InputError :message="fieldForm.errors.field_name" />
                            </div>
                            <div>
                                <InputLabel value="Tipo" />
                                <select
                                    v-model="fieldForm.field_type"
                                    class="mt-1 rounded-md border-gray-300 shadow-sm"
                                >
                                    <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center">
                                    <input v-model="fieldForm.required" type="checkbox" class="rounded border-gray-300" />
                                    <span class="ml-2 text-sm">Requerido</span>
                                </label>
                            </div>
                            <div class="flex items-end">
                                <PrimaryButton :disabled="fieldForm.processing">Agregar campo</PrimaryButton>
                            </div>
                        </form>
                        <ul v-if="dynamicFields.length" class="mt-4 space-y-1">
                            <li
                                v-for="f in dynamicFields"
                                :key="f.id"
                                class="flex items-center justify-between rounded bg-gray-50 px-3 py-2"
                            >
                                <span>{{ f.field_name }} ({{ f.field_type }}) {{ f.required ? '*' : '' }}</span>
                                <button type="button" class="text-red-600" @click="deleteField(f)">Eliminar</button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Lista de clientes (tabla con filtros servidor + paginación 20) -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Lista de clientes</h3>
                                <p class="mt-0.5 text-sm text-gray-500">
                                    Filtros en servidor · {{ clients.per_page ?? 20 }} registros por página
                                </p>
                            </div>
                            <a
                                :href="route('agents.clients.export', agent) + exportQueryString"
                                class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                download
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Descargar Excel
                            </a>
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
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Nombre</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Estado</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Email</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Teléfono</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Documento</th>
                                        <th v-for="f in dynamicFields" :key="f.id" class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ f.field_name }}</th>
                                        <th v-for="v in extractionVars" :key="'ev-' + v.id" class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-emerald-700" :title="v.description || 'Extraído de la conversación por IA'">{{ v.label || v.name }}</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Contactos</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Llamadas</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Cargas</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Alertas de llamada</th>
                                        <th class="whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr
                                        v-for="(client, idx) in clients.data"
                                        :key="client.id"
                                        class="transition-colors hover:bg-[#f0f4f8]"
                                        :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'"
                                    >
                                        <td class="whitespace-nowrap px-3 py-2.5 font-medium text-gray-900">{{ client.name }} {{ client.lastname }}</td>
                                        <td class="px-3 py-2.5">
                                            <span
                                                v-if="client.status"
                                                :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', getStatusClass(client.status)]"
                                            >
                                                {{ getStatusLabel(client.status) }}
                                            </span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="max-w-[200px] truncate px-3 py-2.5 text-gray-700" :title="client.email">{{ client.email }}</td>
                                        <td class="px-3 py-2.5">{{ client.phone || '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700">{{ client.document_type }} {{ client.document }}</td>
                                        <td v-for="f in dynamicFields" :key="f.id" class="max-w-[120px] truncate px-3 py-2.5 text-gray-600" :title="String((client.custom_fields || {})[f.field_name] ?? '')">{{ (client.custom_fields || {})[f.field_name] ?? '-' }}</td>
                                        <td v-for="v in extractionVars" :key="'ev-' + v.id" class="max-w-[160px] truncate px-3 py-2.5 text-gray-700" :title="String((client.extracted_variables || {})[v.name] ?? '')">{{ (client.extracted_variables || {})[v.name] ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600">{{ client.contact_logs_count ?? 0 }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600">{{ client.calls_count ?? 0 }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600">{{ client.load_dates_count ?? 0 }}</td>
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
                                                title="Llamadas y WhatsApp"
                                                @click="openContact(client, 'calls')"
                                            >
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.84L3 20l1.16-3.48A7.98 7.98 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
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
                                        <td :colspan="11 + dynamicFields.length + extractionVars.length" class="px-4 py-10 text-center text-gray-500">Sin clientes con los filtros actuales.</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                        <!-- Paginación -->
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
                        <div>
                            <InputLabel value="Estado" />
                            <select
                                v-model="form.status"
                                class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            >
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <TextInput
                                v-if="form.status === 'otro'"
                                v-model="form.status_custom"
                                class="mt-2 w-full"
                                placeholder="Escribir estado personalizado"
                            />
                            <InputError :message="form.errors.status" />
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

        <!-- Modal único de contacto: pestañas Llamadas / WhatsApp -->
        <ClientContactModal
            :show="showContactModal"
            :agent="agent"
            :client="contactClient"
            :plantillas="plantillas"
            :default-plantilla-id="defaultPlantillaId"
            :initial-tab="contactInitialTab"
            @close="showContactModal = false"
        />

        <!-- Popup de alertas de llamada por cliente -->
        <CallAlertsModal
            :show="showAlertsModal"
            :agent="agent"
            :client="alertsClient"
            @close="showAlertsModal = false"
            @count-changed="onAlertsCountChanged"
        />
    </AuthenticatedLayout>
</template>
