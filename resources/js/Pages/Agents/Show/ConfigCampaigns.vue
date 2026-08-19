<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const queues = ref([]);
const loading = ref(true);
const statusFilter = ref('all');
const cancellingId = ref(null);
const showCreateModal = ref(false);
const showScheduleConfig = ref(false);
const configTab = ref('whatsapp'); // 'whatsapp' | 'call'
const creating = ref(false);
const clientsList = ref([]);
const clientsSearch = ref('');
const selectedClientIds = ref(new Set());
const clientSelectionMode = ref('manual'); // 'manual' | 'by_rules'
const rulesForm = ref([{ field: '', fieldOther: '', operator: 'equals', value: '', delay_days: 0, delay_from: 'last_contacted_at' }]);
const clientsByRulesIds = ref([]);
const loadingByRules = ref(false);

const typeLabels = { call: 'Llamada', whatsapp: 'WhatsApp' };
const statusLabels = {
    all: 'Todas',
    pending: 'Pendiente',
    processing: 'En proceso',
    completed: 'Completado',
    cancelled: 'Cancelado',
};

// Create campaign form: 'immediate' | 'date_time' | 'by_hours'
const createType = ref('whatsapp');
const createScheduleMode = ref('immediate');
const createScheduledDate = ref('');
const createScheduledTime = ref('08:00');
const createPlantillaId = ref('');

const days = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
    { value: 6, label: 'Sábado' },
];

const getConfig = (type) => {
    const config = type === 'whatsapp' ? props.agent?.message_config : props.agent?.call_config;
    return config?.schedule_config || {};
};

const msgConfig = computed(() => getConfig('whatsapp'));
const callConfig = computed(() => getConfig('call'));

const plantillas = computed(() => props.agent?.message_config?.plantillas || []);

const initScheduleForm = (type) => {
    const sc = getConfig(type);
    return {
        days_of_week: Array.isArray(sc.days_of_week) ? sc.days_of_week.map((d) => (typeof d === 'object' ? { ...d } : { day: d, offset_hours: 0 })) : [],
        excluded_dates: Array.isArray(sc.excluded_dates) ? [...sc.excluded_dates] : [],
        campaign_slots: Array.isArray(sc.campaign_slots) && sc.campaign_slots.length
            ? sc.campaign_slots.map((s) => ({
                time: s.time || '09:00',
                rule_ids: Array.isArray(s.rule_ids) ? [...s.rule_ids] : [],
                id_plantilla: s.id_plantilla ?? '',
            }))
            : [{ time: '09:00', rule_ids: [], id_plantilla: '' }],
        campaign_timezone: sc.campaign_timezone ?? 'America/Bogota',
        execution_rules: Array.isArray(sc.execution_rules) ? sc.execution_rules.map((r) => ({
            ...r,
            fieldOther: r.fieldOther ?? '',
            value: Array.isArray(r.value) ? r.value.join(',') : (r.value ?? ''),
            delay_days: r.delay_days ?? 0,
            delay_from: r.delay_from ?? 'loaded_at',
        })) : [],
    };
};

const scheduleForm = ref({ whatsapp: null, call: null });

const fieldOptions = computed(() => {
    const opts = [
        { value: '', label: '— Seleccionar —' },
        { value: 'name', label: 'Nombre' },
        { value: 'email', label: 'Email' },
        { value: 'phone', label: 'Teléfono' },
        { value: 'contact_logs_count', label: 'Veces contactado' },
        { value: 'last_contacted_at', label: 'Último contacto (fecha)' },
    ];
    (props.agent?.client_fields || props.agent?.clientFields || []).forEach((f) => {
        const key = f.field_name || f.fieldName;
        if (key) opts.push({ value: `custom_fields.${key}`, label: `Campo: ${key}` });
    });
    opts.push({ value: '__other__', label: '— Otro —' });
    return opts;
});

const delayFromOptions = [
    { value: 'loaded_at', label: 'Fecha de carga' },
    { value: 'created_at', label: 'Fecha de creación' },
    { value: 'updated_at', label: 'Última actualización' },
    { value: 'last_contacted_at', label: 'Último contacto' },
];

const ruleOperators = [
    { value: 'equals', label: 'es igual a' },
    { value: 'not_equals', label: 'no es igual a' },
    { value: 'in', label: 'está en (separar por coma)' },
    { value: 'not_in', label: 'no está en' },
    { value: 'gte', label: '≥ (mayor o igual)' },
    { value: 'lte', label: '≤ (menor o igual)' },
    { value: 'gt', label: '> (mayor que)' },
    { value: 'lt', label: '< (menor que)' },
];

function ensureScheduleForm(type) {
    if (!scheduleForm.value[type]) {
        scheduleForm.value[type] = initScheduleForm(type);
    }
    return scheduleForm.value[type];
}

async function loadQueues() {
    loading.value = true;
    try {
        const params = {};
        if (statusFilter.value !== 'all') params.status = statusFilter.value;
        const { data } = await axios.get(route('agents.contact-queues.index', props.agent), { params });
        queues.value = data.contact_queues || [];
    } catch {
        queues.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadClients() {
    try {
        const { data } = await axios.get(route('agents.clients.for-campaign', props.agent), {
            params: { search: clientsSearch.value || undefined },
        });
        clientsList.value = data.clients || [];
    } catch {
        clientsList.value = [];
    }
}

function formatDate(d) {
    if (!d) return '-';
    const str = typeof d === 'string' ? d : (d.toISOString?.() ?? '');
    if (!str) return '-';
    try {
        const dt = new Date(str);
        return dt.toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return str;
    }
}

function canCancel(q) {
    return q.status === 'pending' || q.status === 'processing';
}

async function cancelQueue(q) {
    if (!confirm('¿Cancelar esta campaña? No se ejecutará ni se contactará a los clientes pendientes.')) return;
    cancellingId.value = q.id;
    try {
        const { data } = await axios.post(route('agents.contact-queues.cancel', [props.agent, q.id]));
        if (data.success) {
            toast.success(data.message);
            loadQueues();
        } else {
            toast.error(data.message || 'Error al cancelar.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al cancelar la campaña.');
    } finally {
        cancellingId.value = null;
    }
}

function openCreateModal() {
    showCreateModal.value = true;
    createType.value = 'whatsapp';
    createScheduleMode.value = 'immediate';
    createScheduledDate.value = '';
    createScheduledTime.value = '08:00';
    createPlantillaId.value = props.agent?.message_config?.default_plantilla_id || '';
    selectedClientIds.value = new Set();
    clientSelectionMode.value = 'manual';
    rulesForm.value = [{ field: '', fieldOther: '', operator: 'equals', value: '', delay_days: 0, delay_from: 'last_contacted_at' }];
    clientsByRulesIds.value = [];
    modalScheduleForm.value = null;
    loadClients();
}

const selectedWithPhone = computed(() => {
    const list = clientsList.value.filter((c) => selectedClientIds.value.has(c.id));
    return createType.value === 'whatsapp' ? list.filter((c) => c.phone) : list;
});

const selectedClientCount = computed(() => {
    if (clientSelectionMode.value === 'by_rules') {
        return clientsByRulesIds.value.length > 0
            ? `${clientsByRulesIds.value.length} (vista previa)`
            : 'por reglas al ejecutar';
    }
    return selectedWithPhone.value.length;
});

function addRule() {
    rulesForm.value.push({ field: '', fieldOther: '', operator: 'equals', value: '', delay_days: 0, delay_from: 'last_contacted_at' });
}

function removeRule(idx) {
    rulesForm.value.splice(idx, 1);
    if (rulesForm.value.length === 0) rulesForm.value.push({ field: '', fieldOther: '', operator: 'equals', value: '', delay_days: 0, delay_from: 'last_contacted_at' });
}

async function fetchClientsByRules() {
    const validRules = rulesForm.value.filter((r) => (r.field === '__other__' ? r.fieldOther?.trim() : r?.field?.trim()));
    if (!validRules.length) {
        toast.error('Agrega al menos una regla con campo seleccionado.');
        return;
    }
    loadingByRules.value = true;
    try {
        const rules = validRules.map((r) => {
            const field = (r.field === '__other__' ? (r.fieldOther || '').trim() : r.field) || '';
            let value = r.value ?? '';
            if (['in', 'not_in'].includes(r.operator) && typeof value === 'string') {
                value = value.split(',').map((v) => v.trim()).filter(Boolean);
            }
            const rule = { field, operator: r.operator || 'equals', value };
            const delayDays = Math.max(0, parseInt(r.delay_days, 10) || 0);
            if (delayDays > 0) {
                rule.delay_days = delayDays;
                rule.delay_from = r.delay_from || 'last_contacted_at';
            }
            return rule;
        });
        const { data } = await axios.post(route('agents.clients.by-rules', props.agent), { rules });
        clientsByRulesIds.value = data.client_ids || [];
        toast.success(`${clientsByRulesIds.value.length} cliente(s) coinciden con las reglas.`);
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al buscar clientes.');
        clientsByRulesIds.value = [];
    } finally {
        loadingByRules.value = false;
    }
}

function getFieldOptionsForRule(rule) {
    const base = fieldOptions.value;
    if (rule.field && rule.field !== '__other__' && !base.some((o) => o.value === rule.field)) {
        return [{ value: rule.field, label: rule.field }, ...base];
    }
    return base;
}

const hasCallSchedule = () => {
    const sc = props.agent?.call_config?.schedule_config;
    const times = (sc?.campaign_slots || []).map((s) => s?.time).filter(Boolean) || sc?.campaign_times || [];
    const days = sc?.days_of_week || [];
    return times.length > 0 && days.length > 0;
};

const hasWhatsappSchedule = () => {
    const sc = props.agent?.message_config?.schedule_config;
    const times = (sc?.campaign_slots || []).map((s) => s?.time).filter(Boolean) || sc?.campaign_times || [];
    const days = sc?.days_of_week || [];
    return times.length > 0 && days.length > 0;
};

const canScheduleByHours = () => {
    return createType.value === 'call' ? hasCallSchedule() : hasWhatsappSchedule();
};

// Horarios en el modal (para programar según horarios)
const modalScheduleForm = ref(null);

function initModalScheduleForm() {
    modalScheduleForm.value = initScheduleForm(createType.value);
}

watch([createScheduleMode, createType], () => {
    if (createScheduleMode.value === 'by_hours') {
        initModalScheduleForm();
    }
});

const hasValidModalSchedule = () => {
    if (!modalScheduleForm.value) return false;
    const f = modalScheduleForm.value;
    const days = f.days_of_week || [];
    const slots = (f.campaign_slots || []).filter((s) => s?.time);
    return days.length > 0 && slots.length > 0;
};

function toggleModalDay(dayValue) {
    if (!modalScheduleForm.value) return;
    const arr = modalScheduleForm.value.days_of_week;
    const idx = arr.findIndex((d) => d.day === dayValue);
    if (idx >= 0) arr.splice(idx, 1);
    else arr.push({ day: dayValue, offset_hours: 0 });
}

function addModalSlot() {
    if (!modalScheduleForm.value) return;
    const slots = modalScheduleForm.value.campaign_slots;
    const base = { time: '09:00', rule_ids: [] };
    if (createType.value === 'whatsapp') base.id_plantilla = '';
    slots.push(base);
}

function removeModalSlot(idx) {
    if (!modalScheduleForm.value) return;
    modalScheduleForm.value.campaign_slots.splice(idx, 1);
}

async function submitCreate() {
    let payload = { type: createType.value, immediate: createScheduleMode.value === 'immediate' };

    if (clientSelectionMode.value === 'by_rules') {
        const validRules = rulesForm.value.filter((r) => (r.field === '__other__' ? r.fieldOther?.trim() : r?.field?.trim()));
        if (!validRules.length) {
            toast.error('Define al menos una regla con campo seleccionado.');
            return;
        }
        const rules = validRules.map((r) => {
            const field = (r.field === '__other__' ? (r.fieldOther || '').trim() : r.field) || '';
            let value = r.value ?? '';
            if (['in', 'not_in'].includes(r.operator) && typeof value === 'string') {
                value = value.split(',').map((v) => v.trim()).filter(Boolean);
            }
            const rule = { field, operator: r.operator || 'equals', value };
            const delayDays = Math.max(0, parseInt(r.delay_days, 10) || 0);
            if (delayDays > 0) {
                rule.delay_days = delayDays;
                rule.delay_from = r.delay_from || 'last_contacted_at';
            }
            return rule;
        });
        payload.client_selection_rules = rules;
        payload.client_ids = [];
    } else {
        const ids = Array.from(selectedClientIds.value);
        const withPhone = createType.value === 'whatsapp'
            ? clientsList.value.filter((c) => ids.includes(c.id) && c.phone).map((c) => c.id)
            : ids;
        if (withPhone.length === 0) {
            toast.error(createType.value === 'whatsapp' ? 'Selecciona al menos un cliente con teléfono.' : 'Selecciona al menos un cliente.');
            return;
        }
        payload.client_ids = withPhone;
    }

    if (createScheduleMode.value === 'date_time' && !createScheduledDate.value) {
        toast.error('Selecciona la fecha.');
        return;
    }
    if (createScheduleMode.value === 'by_hours') {
        if (!hasValidModalSchedule()) {
            toast.error('Configura al menos un día y un horario en la sección de horarios.');
            return;
        }
    }

    const immediate = createScheduleMode.value === 'immediate';
    const useScheduledDate = createScheduleMode.value === 'date_time';

    creating.value = true;
    try {
        if (createType.value === 'whatsapp' && immediate && createPlantillaId.value) {
            payload.id_plantilla = createPlantillaId.value;
        }
        if (useScheduledDate && createScheduledDate.value) {
            payload.scheduled_date = createScheduledDate.value;
            payload.scheduled_time = (createScheduledTime.value || '08:00').substring(0, 5);
        }
        if (createScheduleMode.value === 'by_hours' && modalScheduleForm.value) {
            const f = modalScheduleForm.value;
            payload.schedule_config = {
                days_of_week: f.days_of_week,
                excluded_dates: (f.excluded_dates || []).filter(Boolean),
                campaign_slots: (f.campaign_slots || []).filter((s) => s?.time).map((s) => {
                    const base = { time: s.time, rule_ids: (s.rule_ids || []).filter((i) => Number.isInteger(i) && i >= 0) };
                    if (createType.value === 'whatsapp') base.id_plantilla = (s.id_plantilla || '').trim() || null;
                    return base;
                }),
                campaign_timezone: f.campaign_timezone || 'America/Bogota',
            };
        }

        const { data } = await axios.post(route('agents.contact-queues.store', props.agent), payload);
        if (data.success) {
            toast.success(data.message);
            showCreateModal.value = false;
            loadQueues();
        } else {
            toast.error(data.message || 'Error al crear la campaña.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al crear la campaña.');
    } finally {
        creating.value = false;
    }
}

function toggleClient(id) {
    const next = new Set(selectedClientIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedClientIds.value = next;
}

function toggleAllClients() {
    const list = createType.value === 'whatsapp'
        ? clientsList.value.filter((c) => c.phone)
        : clientsList.value;
    const allIds = new Set(list.map((c) => c.id));
    if (selectedClientIds.value.size >= allIds.size) {
        selectedClientIds.value = new Set();
    } else {
        selectedClientIds.value = new Set(allIds);
    }
}

// Schedule config save
async function saveScheduleConfig(type) {
    const form = ensureScheduleForm(type);
    const existing = getConfig(type);
    const slots = form.campaign_slots.filter((s) => s?.time).map((s) => {
        const base = { time: s.time, rule_ids: (s.rule_ids || []).filter((i) => Number.isInteger(i) && i >= 0) };
        if (type === 'whatsapp') base.id_plantilla = (s.id_plantilla || '').trim() || null;
        return base;
    });
    const scheduleConfig = {
        hours: existing.hours ?? { start: '09:00', end: '18:00' },
        days_of_week: form.days_of_week,
        excluded_dates: form.excluded_dates.filter(Boolean),
        campaign_slots: slots,
        campaign_timezone: form.campaign_timezone,
        execution_rules: (form.execution_rules || []).filter((r) => (r.field === '__other__' ? r.fieldOther?.trim() : r?.field?.trim())).map((r) => ({
            field: (r.field === '__other__' ? (r.fieldOther || '').trim() : r.field.trim()),
            operator: r.operator || 'equals',
            value: ['in', 'not_in'].includes(r.operator) && typeof r.value === 'string'
                ? r.value.split(',').map((v) => v.trim()).filter(Boolean)
                : (r.value ?? ''),
            delay_days: Math.max(0, parseInt(r.delay_days, 10) || 0),
            delay_from: r.delay_from || 'loaded_at',
        })),
    };
    const data = { schedule_config: scheduleConfig };
    const routeName = type === 'whatsapp' ? 'agents.message-config.update' : 'agents.call-config.update';
    router.put(route(routeName, props.agent), data, {
        preserveScroll: true,
        onSuccess: () => toast.success('Configuración guardada.'),
        onError: () => toast.error('Error al guardar.'),
    });
}

watch(showScheduleConfig, (v) => {
    if (v) ensureScheduleForm(configTab.value);
});

onMounted(() => loadQueues());
</script>

<template>
    <div class="space-y-6">
        <!-- Header + Crear campaña -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Campañas</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Crea campañas masivas de llamadas o WhatsApp. Configura horarios y crea campañas desde aquí.
                        </p>
                    </div>
                    <PrimaryButton @click="openCreateModal">
                        + Crear campaña
                    </PrimaryButton>
                </div>

                <!-- Configuración de horarios (collapsible) -->
                <div class="mt-6">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] px-4 py-3 text-left text-sm font-medium text-[#33475b] hover:bg-[#e3e8ee]"
                        @click="showScheduleConfig = !showScheduleConfig"
                    >
                        <span>Configuración de horarios (para "Programar según horarios")</span>
                        <svg :class="['h-5 w-5 transition', showScheduleConfig ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div v-show="showScheduleConfig" class="mt-4 space-y-4 rounded-lg border border-[#e3e8ee] bg-white p-4">
                        <div class="flex gap-2">
                            <button
                                :class="['rounded px-3 py-1.5 text-sm', configTab === 'whatsapp' ? 'bg-[#25D366] text-white' : 'bg-gray-100 text-gray-600']"
                                @click="configTab = 'whatsapp'; ensureScheduleForm('whatsapp')"
                            >
                                WhatsApp
                            </button>
                            <button
                                :class="['rounded px-3 py-1.5 text-sm', configTab === 'call' ? 'bg-[#1976d2] text-white' : 'bg-gray-100 text-gray-600']"
                                @click="configTab = 'call'; ensureScheduleForm('call')"
                            >
                                Llamadas
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">
                            Define días, horarios y reglas. Luego al crear campaña podrás elegir "Programar según horarios configurados".
                        </p>
                        <div v-if="configTab === 'whatsapp' && ensureScheduleForm('whatsapp')" class="space-y-3">
                            <div>
                                <InputLabel value="Días de la semana" />
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <label
                                        v-for="day in days"
                                        :key="day.value"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded border px-2 py-1 text-xs"
                                        :class="scheduleForm.whatsapp.days_of_week.some((d) => d.day === day.value) ? 'border-[#25D366] bg-[#25D366]/10' : 'border-[#e3e8ee]'"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="scheduleForm.whatsapp.days_of_week.some((d) => d.day === day.value)"
                                            @change="
                                                const idx = scheduleForm.whatsapp.days_of_week.findIndex((d) => d.day === day.value);
                                                if (idx >= 0) scheduleForm.whatsapp.days_of_week.splice(idx, 1);
                                                else scheduleForm.whatsapp.days_of_week.push({ day: day.value, offset_hours: 0 });
                                            "
                                        />
                                        {{ day.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Horarios y plantilla por horario" />
                                <p class="mt-0.5 text-xs text-gray-500">Para cada horario elige qué plantilla de WhatsApp se enviará.</p>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <div
                                        v-for="(slot, idx) in scheduleForm.whatsapp.campaign_slots"
                                        :key="idx"
                                        class="flex items-center gap-2 rounded border border-[#e3e8ee] bg-white p-2"
                                    >
                                        <input v-model="slot.time" type="time" class="rounded-md border-[#e3e8ee] text-sm" />
                                        <select
                                            v-if="plantillas.filter((x) => x?.id).length"
                                            v-model="slot.id_plantilla"
                                            class="rounded-md border-[#e3e8ee] text-sm"
                                            title="Plantilla a enviar en este horario"
                                        >
                                            <option value="">Plantilla por defecto</option>
                                            <option v-for="p in plantillas.filter((x) => x?.id)" :key="p.id" :value="p.id">{{ p.name || p.id }}</option>
                                        </select>
                                        <button type="button" class="text-red-600" @click="scheduleForm.whatsapp.campaign_slots.splice(idx, 1)">×</button>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded border border-[#25D366] px-2 py-1 text-xs text-[#25D366]"
                                        @click="scheduleForm.whatsapp.campaign_slots.push({ time: '09:00', rule_ids: [], id_plantilla: '' })"
                                    >
                                        + Hora
                                    </button>
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Zona horaria" />
                                <select v-model="scheduleForm.whatsapp.campaign_timezone" class="mt-1 rounded-md border-[#e3e8ee] text-sm">
                                    <option value="America/Bogota">America/Bogota</option>
                                    <option value="America/Mexico_City">America/Mexico_City</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                            <PrimaryButton class="!py-1.5 !text-sm" @click="saveScheduleConfig('whatsapp')">Guardar WhatsApp</PrimaryButton>
                        </div>
                        <div v-if="configTab === 'call' && ensureScheduleForm('call')" class="space-y-3">
                            <div>
                                <InputLabel value="Días de la semana" />
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <label
                                        v-for="day in days"
                                        :key="day.value"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded border px-2 py-1 text-xs"
                                        :class="scheduleForm.call.days_of_week.some((d) => d.day === day.value) ? 'border-[#1976d2] bg-[#1976d2]/10' : 'border-[#e3e8ee]'"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="scheduleForm.call.days_of_week.some((d) => d.day === day.value)"
                                            @change="
                                                const idx = scheduleForm.call.days_of_week.findIndex((d) => d.day === day.value);
                                                if (idx >= 0) scheduleForm.call.days_of_week.splice(idx, 1);
                                                else scheduleForm.call.days_of_week.push({ day: day.value, offset_hours: 0 });
                                            "
                                        />
                                        {{ day.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Horarios" />
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <div v-for="(slot, idx) in scheduleForm.call.campaign_slots" :key="idx" class="flex items-center gap-2">
                                        <input v-model="slot.time" type="time" class="rounded-md border-[#e3e8ee] text-sm" />
                                        <button type="button" class="text-red-600" @click="scheduleForm.call.campaign_slots.splice(idx, 1)">×</button>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded border border-[#1976d2] px-2 py-1 text-xs text-[#1976d2]"
                                        @click="scheduleForm.call.campaign_slots.push({ time: '09:00', rule_ids: [] })"
                                    >
                                        + Hora
                                    </button>
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Zona horaria" />
                                <select v-model="scheduleForm.call.campaign_timezone" class="mt-1 rounded-md border-[#e3e8ee] text-sm">
                                    <option value="America/Bogota">America/Bogota</option>
                                    <option value="America/Mexico_City">America/Mexico_City</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                            <PrimaryButton class="!py-1.5 !text-sm" @click="saveScheduleConfig('call')">Guardar Llamadas</PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de campañas -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="s in ['all', 'pending', 'processing', 'completed', 'cancelled']"
                        :key="s"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-sm font-medium',
                            statusFilter === s ? 'bg-[var(--color-primary)] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
                        ]"
                        @click="statusFilter = s; loadQueues()"
                    >
                        {{ statusLabels[s] }}
                    </button>
                </div>

                <div v-if="loading" class="mt-6 text-center text-gray-500">Cargando...</div>
                <div v-else-if="!queues.length" class="mt-6 rounded-lg border border-dashed border-[#e3e8ee] bg-gray-50 p-8 text-center text-gray-500">
                    No hay campañas {{ statusFilter === 'all' ? '' : statusFilter === 'pending' ? 'pendientes' : statusFilter === 'completed' ? 'completadas' : statusFilter === 'processing' ? 'en proceso' : 'canceladas' }}.
                </div>
                <div v-else class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-[#f5f8fa]">
                            <tr>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">ID</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Tipo</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Estado</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Clientes</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Próxima ejecución</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Última ejecución</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Creado</th>
                                <th class="border-b border-[#e3e8ee] px-3 py-2 text-right font-medium text-[#33475b]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="q in queues" :key="q.id" class="hover:bg-gray-50">
                                <td class="border-b border-[#e3e8ee] px-3 py-2 font-mono text-xs">{{ q.id }}</td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2">{{ typeLabels[q.type] || q.type }}</td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            q.status === 'pending' ? 'bg-amber-100 text-amber-800' : '',
                                            q.status === 'processing' ? 'bg-blue-100 text-blue-800' : '',
                                            q.status === 'completed' ? 'bg-green-100 text-green-800' : '',
                                            q.status === 'cancelled' ? 'bg-gray-100 text-gray-600' : '',
                                        ]"
                                    >
                                        {{ statusLabels[q.status] }}
                                    </span>
                                </td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2">
                                    <template v-if="q.by_rules">
                                        {{ q.processed_count ?? 0 }}/por reglas
                                        <span v-if="(q.failed_count ?? 0) > 0" class="text-red-600">({{ q.failed_count }} fallidos)</span>
                                    </template>
                                    <template v-else>
                                        {{ q.processed_count ?? 0 }}/{{ q.client_count ?? 0 }}
                                        <span v-if="(q.failed_count ?? 0) > 0" class="text-red-600">({{ q.failed_count }} fallidos)</span>
                                    </template>
                                </td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2">{{ formatDate(q.next_run_at) }}</td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2">{{ formatDate(q.last_run_at) }}</td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2">{{ formatDate(q.created_at) }}</td>
                                <td class="border-b border-[#e3e8ee] px-3 py-2 text-right">
                                    <button
                                        v-if="canCancel(q)"
                                        type="button"
                                        class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                                        :disabled="cancellingId === q.id"
                                        @click="cancelQueue(q)"
                                    >
                                        {{ cancellingId === q.id ? 'Cancelando...' : 'Cancelar' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Crear campaña -->
    <div v-show="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="showCreateModal = false">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false" />
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-medium text-gray-900">Crear campaña</h3>
                <p class="mt-1 text-sm text-gray-500">Selecciona clientes, tipo y cuándo ejecutar.</p>

                <div class="mt-6 space-y-4">
                    <div>
                        <InputLabel value="Tipo" />
                        <div class="mt-1 flex gap-2">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded border px-3 py-2" :class="createType === 'whatsapp' ? 'border-[#25D366] bg-[#25D366]/10' : 'border-[#e3e8ee]'">
                                <input v-model="createType" type="radio" value="whatsapp" />
                                WhatsApp
                            </label>
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded border px-3 py-2" :class="createType === 'call' ? 'border-[#1976d2] bg-[#1976d2]/10' : 'border-[#e3e8ee]'">
                                <input v-model="createType" type="radio" value="call" />
                                Llamada
                            </label>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="¿Cuándo ejecutar?" />
                        <div class="mt-1 space-y-2">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model="createScheduleMode" type="radio" value="immediate" />
                                Ejecutar ahora
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model="createScheduleMode" type="radio" value="date_time" />
                                Programar en fecha y hora específica
                            </label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input v-model="createScheduleMode" type="radio" value="by_hours" />
                                Programar según horarios configurados
                            </label>
                        </div>
                    </div>

                    <div v-if="createType === 'whatsapp' && createScheduleMode === 'immediate' && plantillas.length" class="rounded border border-[#e3e8ee] p-3">
                        <InputLabel value="Plantilla" />
                        <select v-model="createPlantillaId" class="mt-1 w-full rounded-md border-[#e3e8ee] text-sm">
                            <option value="">— Por defecto —</option>
                            <option v-for="p in plantillas.filter((x) => x?.id)" :key="p.id" :value="p.id">{{ p.name || p.id }}</option>
                        </select>
                    </div>

                    <!-- Horarios (cuando programar según horarios) -->
                    <div v-if="createScheduleMode === 'by_hours'" class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                        <h4 class="text-sm font-medium text-gray-700">Horarios de ejecución</h4>
                        <p class="mt-1 text-xs text-gray-500">Confirma o modifica los días y horarios. La campaña se ejecutará en la próxima coincidencia.</p>
                        <div v-if="modalScheduleForm" class="mt-4 space-y-3">
                            <div>
                                <InputLabel value="Días de la semana" />
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <label
                                        v-for="day in days"
                                        :key="day.value"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded border px-2 py-1 text-xs"
                                        :class="modalScheduleForm.days_of_week.some((d) => d.day === day.value) ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[#e3e8ee]'"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="modalScheduleForm.days_of_week.some((d) => d.day === day.value)"
                                            @change="toggleModalDay(day.value)"
                                        />
                                        {{ day.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <InputLabel :value="createType === 'whatsapp' ? 'Horarios y plantilla por horario' : 'Horarios'" />
                                <p v-if="createType === 'whatsapp'" class="mt-0.5 text-xs text-gray-500">Para cada horario elige qué plantilla se enviará.</p>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <div v-for="(slot, idx) in modalScheduleForm.campaign_slots" :key="idx" class="flex items-center gap-2 rounded border border-[#e3e8ee] bg-white p-2">
                                        <input v-model="slot.time" type="time" class="rounded-md border-[#e3e8ee] text-sm" />
                                        <select
                                            v-if="createType === 'whatsapp' && plantillas.filter((x) => x?.id).length"
                                            v-model="slot.id_plantilla"
                                            class="rounded-md border-[#e3e8ee] text-sm"
                                            title="Plantilla a enviar en este horario"
                                        >
                                            <option value="">Plantilla por defecto</option>
                                            <option v-for="p in plantillas.filter((x) => x?.id)" :key="p.id" :value="p.id">{{ p.name || p.id }}</option>
                                        </select>
                                        <button type="button" class="text-red-600 hover:text-red-700" @click="removeModalSlot(idx)">×</button>
                                    </div>
                                    <button type="button" class="rounded border border-[var(--color-primary)] px-2 py-1 text-xs text-[var(--color-primary)]" @click="addModalSlot">
                                        + Hora
                                    </button>
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Zona horaria" />
                                <select v-model="modalScheduleForm.campaign_timezone" class="mt-1 rounded-md border-[#e3e8ee] text-sm">
                                    <option value="America/Bogota">America/Bogota</option>
                                    <option value="America/Mexico_City">America/Mexico_City</option>
                                    <option value="America/Argentina/Buenos_Aires">America/Argentina/Buenos_Aires</option>
                                    <option value="America/Lima">America/Lima</option>
                                    <option value="America/Santiago">America/Santiago</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div v-if="createScheduleMode === 'date_time'" class="flex gap-4">
                        <div class="flex-1">
                            <InputLabel value="Fecha *" />
                            <input v-model="createScheduledDate" type="date" :min="new Date().toISOString().split('T')[0]" class="mt-1 w-full rounded-md border-[#e3e8ee] text-sm" />
                        </div>
                        <div class="w-32">
                            <InputLabel value="Hora" />
                            <input v-model="createScheduledTime" type="time" class="mt-1 w-full rounded-md border-[#e3e8ee] text-sm" />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Clientes" />
                            <div class="flex gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm">
                                    <input v-model="clientSelectionMode" type="radio" value="manual" />
                                    Manual
                                </label>
                                <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm">
                                    <input v-model="clientSelectionMode" type="radio" value="by_rules" />
                                    Por reglas
                                </label>
                            </div>
                        </div>

                        <!-- Por reglas -->
                        <div v-if="clientSelectionMode === 'by_rules'" class="mt-4 space-y-3 rounded-lg border border-indigo-200 bg-indigo-50/30 p-4">
                            <p class="text-xs text-gray-600">Define condiciones. Al ejecutarse la campaña, se contactará a todos los clientes que cumplan las reglas en ese momento (no se fija la lista al crear).</p>
                            <div v-for="(rule, idx) in rulesForm" :key="idx" class="space-y-2 rounded border border-[#e3e8ee] bg-white p-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <select
                                        v-model="rule.field"
                                        class="min-w-[140px] rounded-md border-[#e3e8ee] text-sm"
                                        @change="rule.field !== '__other__' && (rule.fieldOther = '')"
                                    >
                                        <option v-for="o in getFieldOptionsForRule(rule)" :key="o.value" :value="o.value">{{ o.label }}</option>
                                    </select>
                                    <input
                                        v-if="rule.field === '__other__'"
                                        v-model="rule.fieldOther"
                                        type="text"
                                        placeholder="custom_fields.estado"
                                        class="min-w-[120px] rounded-md border-[#e3e8ee] text-sm"
                                    />
                                    <select v-model="rule.operator" class="rounded-md border-[#e3e8ee] text-sm">
                                        <option v-for="op in ruleOperators" :key="op.value" :value="op.value">{{ op.label }}</option>
                                    </select>
                                    <input
                                        v-model="rule.value"
                                        type="text"
                                        :placeholder="['in','not_in'].includes(rule.operator) ? 'activo, pendiente' : ['gte','lte','gt','lt'].includes(rule.operator) ? '2' : 'activo'"
                                        class="min-w-[80px] rounded-md border-[#e3e8ee] text-sm"
                                    />
                                    <button type="button" class="text-red-600 hover:text-red-700" @click="removeRule(idx)">×</button>
                                </div>
                                <div v-if="rule.field || rule.fieldOther" class="flex flex-wrap items-center gap-2 border-t border-[#e3e8ee] pt-2 text-xs text-gray-600">
                                    <span>Opcional: solo si han pasado</span>
                                    <input
                                        v-model.number="rule.delay_days"
                                        type="number"
                                        min="0"
                                        max="365"
                                        placeholder="0"
                                        class="w-14 rounded-md border-[#e3e8ee] text-sm"
                                    />
                                    <span>días desde</span>
                                    <select v-model="rule.delay_from" class="rounded-md border-[#e3e8ee] text-sm">
                                        <option v-for="d in delayFromOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
                                    </select>
                                    <span v-if="rule.delay_days > 0" class="text-indigo-600">(ej: si contactó 2 veces, llamar al día siguiente)</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded border border-indigo-300 px-2 py-1 text-sm text-indigo-600" @click="addRule">+ Agregar regla</button>
                                <PrimaryButton class="!py-1.5 !text-sm" :disabled="loadingByRules" @click="fetchClientsByRules">
                                    {{ loadingByRules ? 'Buscando...' : 'Vista previa (opcional)' }}
                                </PrimaryButton>
                            </div>
                            <p v-if="clientsByRulesIds.length > 0" class="text-sm font-medium text-green-700">
                                Vista previa: {{ clientsByRulesIds.length }} cliente(s) coinciden ahora. La campaña contactará a quienes cumplan las reglas al ejecutarse.
                            </p>
                        </div>

                        <!-- Manual -->
                        <template v-else>
                            <div class="mt-2 flex gap-2">
                                <input
                                    v-model="clientsSearch"
                                    type="text"
                                    placeholder="Buscar..."
                                    class="rounded-md border-[#e3e8ee] text-sm"
                                    @keydown.enter.prevent="loadClients()"
                                />
                                <button type="button" class="text-sm text-indigo-600" @click="loadClients()">Buscar</button>
                                <button type="button" class="text-sm text-indigo-600" @click="toggleAllClients()">
                                    {{ selectedClientIds.size >= (createType === 'whatsapp' ? clientsList.filter(c => c.phone).length : clientsList.length) ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                                </button>
                            </div>
                            <div class="mt-2 max-h-48 overflow-y-auto rounded border border-[#e3e8ee]">
                                <div v-if="!clientsList.length" class="p-4 text-center text-gray-500">Carga clientes con la búsqueda.</div>
                                <label
                                    v-for="c in clientsList"
                                    :key="c.id"
                                    class="flex cursor-pointer items-center gap-2 border-b border-[#e3e8ee] px-3 py-2 last:border-0 hover:bg-gray-50"
                                    :class="{ 'bg-[#25D366]/10': createType === 'whatsapp' && !c.phone && selectedClientIds.has(c.id) }"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="selectedClientIds.has(c.id)"
                                        :disabled="createType === 'whatsapp' && !c.phone"
                                        @change="toggleClient(c.id)"
                                    />
                                    <span>{{ c.name }} {{ c.lastname }}</span>
                                    <span v-if="c.phone" class="text-xs text-gray-500">{{ c.phone }}</span>
                                    <span v-else-if="createType === 'whatsapp'" class="text-xs text-amber-600">Sin teléfono</span>
                                </label>
                            </div>
                        </template>

                        <p class="mt-2 text-xs text-gray-500">
                            {{ selectedClientCount }} cliente(s){{ clientSelectionMode === 'manual' && createType === 'whatsapp' ? ' con teléfono' : '' }} para la campaña.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded border px-4 py-2 hover:bg-gray-50" @click="showCreateModal = false">Cancelar</button>
                    <PrimaryButton :disabled="creating" @click="submitCreate">
                        {{ creating ? 'Creando...' : 'Crear campaña' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </div>
</template>
