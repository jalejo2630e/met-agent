<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import html2canvas from 'html2canvas';
import { toast } from 'vue3-toastify';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';
import ReportWidget from './Reports/ReportWidget.vue';
import ReportWidgetForm from './Reports/ReportWidgetForm.vue';
import ClientDetailModal from './Reports/ClientDetailModal.vue';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
    agent: Object,
});

const dynamicFields = computed(() => props.agent?.client_fields || props.agent?.clientFields || []);

/** Datos recolectados que no son campos dinámicos (evita duplicar en la tabla) */
function extraCollectedData(c) {
    const cf = c.custom_fields || {};
    const names = dynamicFields.value.map(f => f.field_name);
    return Object.fromEntries(Object.entries(cf).filter(([key]) => !names.includes(key)));
}

const exportCollectionUrl = computed(() => {
    let url = route('agents.clients.export-collection', props.agent);
    if (filterMode.value === 'range' && (dateFrom.value || dateTo.value)) {
        const params = new URLSearchParams();
        if (dateFrom.value) params.set('date_from', dateFrom.value);
        if (dateTo.value) params.set('date_to', dateTo.value);
        url += '?' + params.toString();
    }
    return url;
});

const collectionChartData = computed(() => {
    const r = report.value;
    if (!r) return null;
    const withCol = r.total_with_collection ?? 0;
    const without = Math.max(0, (r.total_clients ?? 0) - withCol);
    return {
        labels: ['Con datos recolectados', 'Sin datos recolectados'],
        datasets: [{
            data: [withCol, without],
            backgroundColor: ['rgba(46, 125, 50, 0.8)', 'rgba(158, 158, 158, 0.5)'],
            borderColor: ['#2e7d32', '#9e9e9e'],
            borderWidth: 1,
        }],
    };
});

const collectionChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
    },
};

const loading = ref(false);
const report = ref(null);
const filterMode = ref('all'); // 'all' | 'range'
const dateFrom = ref('');
const dateTo = ref('');

// ---- Reportes personalizados (widgets) ----
// Nota: se declaran ANTES del watch inmediato para evitar el "temporal dead zone"
// (el watch con immediate:true corre durante el setup y llama a fetchWidgets()).
const reportWidgets = ref([]);
const widgetsLoading = ref(false);
const showWidgetForm = ref(false);
const editingWidget = ref(null);

async function fetchReport() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterMode.value === 'range') {
            if (dateFrom.value) params.set('date_from', dateFrom.value);
            if (dateTo.value) params.set('date_to', dateTo.value);
        }
        const url = route('agents.reports.index', props.agent.id) + (params.toString() ? '?' + params.toString() : '');
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        report.value = await response.json();
        if (report.value?.default_email && !sendEmail.value) {
            sendEmail.value = report.value.default_email;
        }
    } catch (e) {
        report.value = { total_clients: 0, total_messages: 0, total_calls: 0 };
    } finally {
        loading.value = false;
    }
}

watch(filterMode, () => {
    if (filterMode.value === 'all') {
        fetchReport();
        fetchWidgets();
    }
}, { immediate: true });

function applyFilter() {
    fetchReport();
    fetchWidgets();
}

function filterParams() {
    const params = new URLSearchParams();
    if (filterMode.value === 'range') {
        if (dateFrom.value) params.set('date_from', dateFrom.value);
        if (dateTo.value) params.set('date_to', dateTo.value);
    }
    return params;
}

async function fetchWidgets() {
    widgetsLoading.value = true;
    try {
        const params = filterParams();
        const url = route('agents.report-widgets.data', props.agent.id) + (params.toString() ? '?' + params.toString() : '');
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await response.json();
        reportWidgets.value = json.widgets || [];
    } catch (e) {
        reportWidgets.value = [];
    } finally {
        widgetsLoading.value = false;
    }
}

function openCreate() {
    editingWidget.value = null;
    showWidgetForm.value = true;
}

function openEdit(widget) {
    editingWidget.value = widget;
    showWidgetForm.value = true;
}

function deleteWidget(widget) {
    if (!confirm(`¿Eliminar el reporte "${widget.title}"?`)) return;
    router.delete(route('agents.report-widgets.destroy', [props.agent.id, widget.id]), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Reporte eliminado.');
            fetchWidgets();
        },
    });
}

function onWidgetSaved() {
    fetchWidgets();
}

// ---- Detalle de cliente ----
const showClientDetail = ref(false);
const detailClientId = ref(null);
const detailClientName = ref('');

function openClientDetail(row) {
    detailClientId.value = row.id;
    detailClientName.value = row.name || '';
    showClientDetail.value = true;
}

// ---- Reordenar por drag & drop ----
const dragSrc = ref(null);
const dragOver = ref(null);

// El arrastre solo inicia desde el "handle"; se activa el atributo draggable
// del contenedor de forma síncrona para que dragstart dispare de inmediato.
function onHandleDown(e) {
    const card = e.target.closest('[data-card]');
    if (card) card.setAttribute('draggable', 'true');
}

function onDragStart(index, e) {
    dragSrc.value = index;
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', String(index)); } catch (_) {}
}

function onDragOver(index) {
    dragOver.value = index;
}

function onDrop(index) {
    const from = dragSrc.value;
    if (from === null || from === index) return;
    const arr = [...reportWidgets.value];
    const [moved] = arr.splice(from, 1);
    arr.splice(index, 0, moved);
    reportWidgets.value = arr;
    persistOrder();
}

function onDragEnd() {
    document.querySelectorAll('[data-card][draggable="true"]').forEach((el) => el.setAttribute('draggable', 'false'));
    dragSrc.value = null;
    dragOver.value = null;
}

function persistOrder() {
    const ids = reportWidgets.value.map((w) => w.id);
    router.post(route('agents.report-widgets.reorder', props.agent.id), { ids }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            toast.error('No se pudo guardar el orden.');
            fetchWidgets();
        },
    });
}

// Envío del reporte por correo
const showSend = ref(false);
const sending = ref(false);
const sendEmail = ref('');

// Referencias al DOM de cada tarjeta para capturarla como imagen (WYSIWYG).
const widgetEls = new Map(); // id -> HTMLElement
function setWidgetEl(id, el) {
    if (el) widgetEls.set(id, el);
    else widgetEls.delete(id);
}
const collectionCard = ref(null);

// Etiqueta del período según el filtro visible (se envía tal cual se ve).
const currentPeriodLabel = computed(() => {
    if (filterMode.value !== 'range') return 'Toda la operación';
    return `${dateFrom.value || '—'} a ${dateTo.value || '—'}`;
});

function toggleSend() {
    showSend.value = !showSend.value;
}

/** Convierte un elemento del DOM en una imagen PNG (dataURL) con html2canvas. */
async function captureElement(el, title) {
    const canvas = await html2canvas(el, {
        backgroundColor: '#ffffff',
        scale: 2,
        useCORS: true,
        logging: false,
        // Omite botones de acción (editar/eliminar/arrastrar, descargas) marcados en la UI.
        ignoreElements: (node) =>
            node.nodeType === 1 && typeof node.hasAttribute === 'function' && node.hasAttribute('data-capture-ignore'),
    });
    return { title, image: canvas.toDataURL('image/png') };
}

async function sendReport() {
    if (!sendEmail.value) {
        toast.error('Indica el correo de destino.');
        return;
    }
    sending.value = true;
    try {
        // Asegura que las tarjetas estén renderizadas antes de capturarlas.
        await nextTick();

        const charts = [];
        // Reportes personalizados, en el orden en que se muestran.
        for (const w of reportWidgets.value) {
            const el = widgetEls.get(w.id);
            if (!el) continue;
            try {
                charts.push(await captureElement(el, w.title));
            } catch (_) {
                // Si una tarjeta falla al capturarse, se omite y se continúa.
            }
        }
        // Gráfica fija de datos de recolección.
        if (collectionCard.value) {
            try {
                charts.push(await captureElement(collectionCard.value, 'Datos de recolección'));
            } catch (_) {}
        }

        const { data } = await axios.post(route('agents.reports.send', props.agent.id), {
            email: sendEmail.value,
            date_from: filterMode.value === 'range' ? (dateFrom.value || null) : null,
            date_to: filterMode.value === 'range' ? (dateTo.value || null) : null,
            charts,
        });
        toast.success(data.message || 'Reporte enviado.');
        showSend.value = false;
    } catch (e) {
        const msg = e?.response?.data?.message
            || Object.values(e?.response?.data?.errors || {})[0]?.[0]
            || 'No se pudo enviar el reporte.';
        toast.error(msg);
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- ================= Reportes personalizados ================= -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                <div>
                    <h3 class="text-lg font-semibold text-[#33475b]">Reportes personalizados</h3>
                    <p class="mt-1 text-sm text-[#425b76]">
                        Crea gráficas con tus propias reglas sobre los campos del cliente o las llamadas. Respetan el filtro de fechas del reporte.
                    </p>
                </div>
                <button
                    type="button"
                    @click="openCreate"
                    class="inline-flex items-center gap-2 rounded-md bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)]"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar reporte
                </button>
            </div>
        </div>

        <div v-if="widgetsLoading && !reportWidgets.length" class="rounded-lg border border-[#e3e8ee] bg-white p-12 text-center text-[#425b76]">
            Cargando reportes...
        </div>

        <!-- Estado vacío -->
        <div v-else-if="!reportWidgets.length" class="rounded-lg border border-dashed border-[#cdd8e3] bg-white p-10 text-center">
            <svg class="mx-auto h-10 w-10 text-[#cdd8e3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <p class="mt-3 text-sm font-medium text-[#33475b]">Aún no tienes reportes personalizados</p>
            <p class="mt-1 text-sm text-[#425b76]">Empieza con una plantilla: avance por STEP, satisfacción o llamadas.</p>
            <button
                type="button"
                @click="openCreate"
                class="mt-4 inline-flex items-center gap-2 rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#33475b] transition hover:bg-[#f5f8fa]"
            >
                Crear mi primer reporte
            </button>
        </div>

        <!-- Grid de widgets (arrastra el asa para reordenar) -->
        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div
                v-for="(w, idx) in reportWidgets"
                :key="w.id"
                :ref="(el) => setWidgetEl(w.id, el)"
                :data-card="idx"
                class="rounded-lg transition"
                :class="[
                    w.config?.width === 'full' ? 'lg:col-span-2' : '',
                    {
                        'opacity-40': dragSrc === idx,
                        'ring-2 ring-[var(--color-primary)] ring-offset-2': dragOver === idx && dragSrc !== null && dragSrc !== idx,
                    },
                ]"
                @dragstart="onDragStart(idx, $event)"
                @dragover.prevent="onDragOver(idx)"
                @drop="onDrop(idx)"
                @dragend="onDragEnd"
            >
                <ReportWidget
                    :widget="w"
                    show-handle
                    @edit="openEdit"
                    @delete="deleteWidget"
                    @handledown="onHandleDown"
                    @open-client="openClientDetail"
                />
            </div>
        </div>

        <!-- Filtros -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#33475b]">Filtros de reporte</h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Selecciona un período o consulta toda la operación.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="toggleSend"
                        class="inline-flex items-center gap-2 rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#33475b] transition hover:bg-[#f5f8fa]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Enviar reporte por email
                    </button>
                </div>

                <!-- Panel de envío por correo -->
                <div v-if="showSend" class="mt-4 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4">
                    <p class="text-sm font-medium text-[#33475b]">Enviar reporte de operación por email</p>
                    <p class="mt-1 text-xs text-[#425b76]">
                        Se enviará el reporte con las gráficas tal como se ven en pantalla, según el filtro actual.
                        Cambia el período en los filtros de abajo si necesitas otro rango.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#e3e8ee] bg-white px-3 py-1 text-xs font-medium text-[#33475b]">
                            <span class="inline-block h-2 w-2 rounded-full bg-[var(--color-primary)]"></span>
                            Período: {{ currentPeriodLabel }}
                        </span>
                        <span class="text-xs text-[#425b76]">
                            Incluye {{ reportWidgets.length }} reporte(s) personalizado(s){{ collectionChartData ? ' + datos de recolección' : '' }}.
                        </span>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col">
                            <label class="text-xs font-medium text-[#33475b]">Email de destino</label>
                            <input
                                v-model="sendEmail"
                                type="email"
                                placeholder="destinatario@empresa.com"
                                class="mt-1 rounded-md border border-[#e3e8ee] text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <button
                            type="button"
                            @click="sendReport"
                            :disabled="sending"
                            class="rounded-md bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-50"
                        >
                            {{ sending ? 'Generando y enviando...' : 'Enviar reporte' }}
                        </button>
                        <button
                            type="button"
                            @click="showSend = false"
                            class="text-sm text-[#425b76] hover:text-[#33475b]"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-[#33475b]">Período</label>
                        <select
                            v-model="filterMode"
                            class="mt-1 rounded-md border border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        >
                            <option value="all">Toda la operación</option>
                            <option value="range">Por rango de fechas</option>
                        </select>
                    </div>

                    <template v-if="filterMode === 'range'">
                        <div class="flex flex-col">
                            <label class="text-sm font-medium text-[#33475b]">Desde</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="mt-1 rounded-md border border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                        </div>
                        <div class="flex flex-col">
                            <label class="text-sm font-medium text-[#33475b]">Hasta</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="mt-1 rounded-md border border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                        </div>
                        <button
                            type="button"
                            @click="applyFilter"
                            :disabled="loading"
                            class="rounded-md bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-50"
                        >
                            {{ loading ? 'Cargando...' : 'Aplicar' }}
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div v-if="loading && !report" class="rounded-lg border border-[#e3e8ee] bg-white p-12 text-center text-[#425b76]">
            Cargando reporte...
        </div>

        <div v-else-if="report" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex shrink-0 rounded-lg bg-[#e3f2fd] p-3">
                            <svg class="h-6 w-6 text-[#1976d2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#425b76]">Clientes</p>
                            <p class="text-2xl font-bold text-[#33475b]">{{ report.total_clients }}</p>
                            <p class="text-xs text-[#425b76]">
                                {{ report.period?.all_time ? 'Total histórico' : 'En el período seleccionado' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex shrink-0 rounded-lg bg-[#e8f5e9] p-3">
                            <svg class="h-6 w-6 text-[#2e7d32]" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#425b76]">Mensajes WhatsApp</p>
                            <p class="text-2xl font-bold text-[#33475b]">{{ report.total_messages }}</p>
                            <p class="text-xs text-[#425b76]">
                                Envíos registrados
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex shrink-0 rounded-lg bg-[#fff8e1] p-3">
                            <svg class="h-6 w-6 text-[#f9a825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-[#425b76]">Llamadas</p>
                            <p class="text-2xl font-bold text-[#33475b]">{{ report.total_calls }}</p>
                            <p class="text-xs text-[#425b76]">
                                Llamadas realizadas
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfica datos de recolección -->
        <div v-if="report && collectionChartData" ref="collectionCard" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-[#33475b]">Datos de recolección</h3>
                <p class="mt-1 text-sm text-[#425b76]">Clientes con datos recolectados en conversación (endpoint de recolección).</p>
                <div class="mt-4 flex justify-center" style="height: 260px;">
                    <Doughnut :data="collectionChartData" :options="collectionChartOptions" />
                </div>
                <p class="mt-2 text-center text-sm text-[#425b76]">
                    <strong>{{ report.total_with_collection ?? 0 }}</strong> clientes con datos recolectados
                </p>
                <p class="mt-3 text-center" data-capture-ignore>
                    <a
                        :href="exportCollectionUrl"
                        class="inline-flex items-center gap-1.5 text-sm text-[#1976d2] hover:underline"
                        download
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Descargar Excel (datos recolectados)
                    </a>
                </p>
            </div>
        </div>

        <!-- Información con el cliente -->
        <div v-if="report && report.collection_clients?.length" class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#33475b]">Información recolectada por cliente</h3>
                        <p class="mt-1 text-sm text-[#425b76]">Datos enviados por el endpoint de recolección (conversación WhatsApp) asociados a cada cliente.</p>
                    </div>
                    <a
                        :href="exportCollectionUrl"
                        class="inline-flex items-center gap-1.5 rounded-md border border-[#e3e8ee] bg-white px-3 py-1.5 text-sm text-[#33475b] hover:bg-[#f5f8fa]"
                        download
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Descargar Excel
                    </a>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                        <thead class="bg-[#f5f8fa]">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-[#33475b]">Cliente</th>
                                <th class="px-4 py-2 text-left font-medium text-[#33475b]">Teléfono</th>
                                <th class="px-4 py-2 text-left font-medium text-[#33475b]">Email</th>
                                <th class="px-4 py-2 text-left font-medium text-[#33475b]">Datos recolectados</th>
                                <th class="px-4 py-2 text-left font-medium text-[#33475b]">Veces contactado</th>
                                <th class="px-4 py-2 text-left font-medium text-[#33475b]">Última actualización</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e3e8ee] bg-white">
                            <tr v-for="c in report.collection_clients" :key="c.id" class="hover:bg-[#f5f8fa]/50">
                                <td class="px-4 py-3 text-[#33475b]">{{ c.name }} {{ c.lastname }}</td>
                                <td class="px-4 py-3 text-[#425b76]">{{ c.phone || '—' }}</td>
                                <td class="px-4 py-3 text-[#425b76]">{{ c.email || '—' }}</td>
                                <td class="px-4 py-3">
                                    <ul class="list-inside list-disc space-y-0.5 text-[#425b76]">
                                        <li v-for="(val, key) in extraCollectedData(c)" :key="key">
                                            <span class="font-medium text-[#33475b]">{{ key }}:</span> {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                        </li>
                                        <li v-if="!Object.keys(extraCollectedData(c)).length" class="text-gray-400">—</li>
                                    </ul>
                                </td>
                                <td class="px-4 py-3 text-[#425b76]">{{ c.contact_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-[#425b76]">{{ c.updated_at ? new Date(c.updated_at).toLocaleString() : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="report && report.period?.all_time" class="rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4 text-sm text-[#425b76]">
            <strong>Nota:</strong> El total de mensajes proviene de los registros de contacto (ClientContactLog). El total de llamadas se calcula desde la tabla de registros de llamada (Supabase), asociando cada llamada por el teléfono de los clientes de la empresa.
        </div>

        <ReportWidgetForm
            :show="showWidgetForm"
            :agent="agent"
            :widget="editingWidget"
            @close="showWidgetForm = false"
            @saved="onWidgetSaved"
        />

        <ClientDetailModal
            :show="showClientDetail"
            :agent="agent"
            :client-id="detailClientId"
            :client-name="detailClientName"
            @close="showClientDetail = false"
        />
    </div>
</template>
