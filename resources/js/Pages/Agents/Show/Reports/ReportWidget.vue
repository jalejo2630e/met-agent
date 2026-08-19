<script setup>
import { computed, ref } from 'vue';
import ReportChart from '@/Components/Charts/ReportChart.vue';

const props = defineProps({
    widget: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    showHandle: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'delete', 'handledown', 'open-client']);

const data = computed(() => props.widget.data || {});
const metric = computed(() => props.widget.metric);
const chartType = computed(() => props.widget.chart_type);

const hasData = computed(() => {
    const d = data.value;
    if (metric.value === 'calls') return true;
    if (metric.value === 'client_progress') return (d.with_value ?? 0) > 0;
    if (metric.value === 'topic_progress') return (d.total ?? 0) > 0;
    return (d.values || []).some((v) => Number(v) > 0);
});

/* ---- range_buckets (distribución / embudo) ---- */
const bucketChartType = computed(() => (chartType.value === 'funnel' ? 'horizontalBar' : chartType.value));
const bucketDetail = computed(() => {
    // Para el embudo, mostrar el % respecto al primer tramo (caída).
    if (chartType.value !== 'funnel') return null;
    const values = data.value.values || [];
    const first = values[0] || 0;
    return values.map((v) => {
        const pct = first > 0 ? Math.round((v / first) * 100) : 0;
        return `${pct}% respecto al inicio`;
    });
});

/* ---- client_progress ---- */
const showTable = ref(false);
const selectedTopic = ref(null); // etiqueta de tema | '__completed__' | null (todos)

const COMPLETED_KEY = '__completed__';

// Un cliente "completó" si su valor alcanzó (o superó) el máximo del proceso.
function isCompleted(r) {
    return data.value.max > 0 && Number(r.value) >= Number(data.value.max);
}
const completedCount = computed(() => (data.value.rows || []).filter(isCompleted).length);

const filteredRows = computed(() => {
    const rows = data.value.rows || [];
    const key = selectedTopic.value;
    if (!key) return rows;
    if (key === COMPLETED_KEY) return rows.filter(isCompleted);
    return rows.filter((r) => r.topic === key);
});

const activeFilterLabel = computed(() => {
    if (!selectedTopic.value) return null;
    return selectedTopic.value === COMPLETED_KEY ? 'Completado' : selectedTopic.value;
});

function selectTopic(key) {
    selectedTopic.value = selectedTopic.value === key ? null : key;
    if (selectedTopic.value) showTable.value = true;
}

function clearTopic() {
    selectedTopic.value = null;
}

// La gráfica sigue el filtro activo (muestra los primeros del grupo seleccionado).
const topClients = computed(() => filteredRows.value.slice(0, 12));
const progressChart = computed(() => ({
    labels: topClients.value.map((r) => r.name || '—'),
    values: topClients.value.map((r) => r.overall_pct),
    colors: topClients.value.map((r) => {
        const t = (data.value.topic_averages || []).find((ta) => ta.label === r.topic);
        return t?.color || '#1976d2';
    }),
    detail: topClients.value.map((r) => (r.topic ? `Tema: ${r.topic} · ${r.topic_pct ?? 0}% del tema` : 'Sin tema')),
}));

/* ---- topic_progress ---- */
const TP_COMPLETED = '__completed__';
const TP_NOT_STARTED = '__not_started__';
const tpSelected = ref(null); // etiqueta de tema | TP_COMPLETED | TP_NOT_STARTED | null
const tpDist = computed(() => data.value.distribution || { labels: [], values: [], colors: [] });

function tpMatch(r) {
    const key = tpSelected.value;
    if (!key) return true;
    if (key === TP_COMPLETED) return r.status === 'completed';
    if (key === TP_NOT_STARTED) return r.status === 'not_started';
    return r.status === 'in_progress' && r.topic === key;
}
const tpFilteredRows = computed(() => (data.value.rows || []).filter(tpMatch));
const tpTop = computed(() => tpFilteredRows.value.slice(0, 12));

function tpColor(r) {
    if (r.status === 'completed') return '#2e7d32';
    if (r.status === 'not_started') return '#9e9e9e';
    const t = (data.value.topic_summary || []).find((s) => s.label === r.topic);
    return t?.color || '#1976d2';
}
const tpChart = computed(() => ({
    labels: tpTop.value.map((r) => r.name || '—'),
    values: tpTop.value.map((r) => r.topic_pct ?? 0),
    colors: tpTop.value.map(tpColor),
    detail: tpTop.value.map((r) => {
        if (r.status === 'completed') return 'Completó todos los temas';
        if (r.status === 'not_started') return 'Sin iniciar';
        return `Tema actual: ${r.topic}`;
    }),
}));
const tpActiveLabel = computed(() => {
    const k = tpSelected.value;
    if (!k) return null;
    if (k === TP_COMPLETED) return 'Completado';
    if (k === TP_NOT_STARTED) return 'Sin iniciar';
    return k;
});
function tpSelect(key) {
    tpSelected.value = tpSelected.value === key ? null : key;
    if (tpSelected.value) showTable.value = true;
}
function tpClear() {
    tpSelected.value = null;
}
function tpStatusLabel(r) {
    if (r.status === 'completed') return 'Completado';
    if (r.status === 'not_started') return 'Sin iniciar';
    return r.topic || '—';
}

/* ---- value_counts ---- */

/* ---- calls ---- */
const callMetrics = computed(() => data.value.metrics || []);
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
        <div class="flex items-start justify-between border-b border-[#eef2f6] p-4">
            <div class="flex items-start gap-2">
                <button
                    v-if="showHandle"
                    type="button"
                    data-capture-ignore
                    title="Arrastra para reordenar"
                    class="mt-0.5 cursor-move rounded p-1 text-[#c2cddb] transition hover:bg-[#f5f8fa] hover:text-[#7a8aa0]"
                    @mousedown="emit('handledown', $event)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M7 4a1 1 0 100 2 1 1 0 000-2zM7 9a1 1 0 100 2 1 1 0 000-2zM7 14a1 1 0 100 2 1 1 0 000-2zM13 4a1 1 0 100 2 1 1 0 000-2zM13 9a1 1 0 100 2 1 1 0 000-2zM13 14a1 1 0 100 2 1 1 0 000-2z" />
                    </svg>
                </button>
                <div>
                    <h3 class="text-base font-semibold text-[#33475b]">{{ widget.title }}</h3>
                    <p class="text-xs text-[#425b76]">
                        <span v-if="widget.source === 'calls'">Basado en llamadas</span>
                        <span v-else>Campo: <span class="font-medium">{{ widget.field_name }}</span></span>
                    </p>
                </div>
            </div>
            <div v-if="canEdit" data-capture-ignore class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded p-1.5 text-[#425b76] transition hover:bg-[#f5f8fa] hover:text-[#33475b]"
                    title="Editar"
                    @click="emit('edit', widget)"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="rounded p-1.5 text-[#425b76] transition hover:bg-red-50 hover:text-red-600"
                    title="Eliminar"
                    @click="emit('delete', widget)"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="p-4">
            <div v-if="!hasData" class="flex h-[220px] flex-col items-center justify-center text-center text-sm text-[#98a4b3]">
                <svg class="mb-2 h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Sin datos para este período.
            </div>

            <!-- range_buckets: distribución o embudo -->
            <template v-else-if="metric === 'range_buckets'">
                <ReportChart
                    :type="bucketChartType"
                    :labels="data.labels"
                    :values="data.values"
                    :colors="data.colors"
                    :detail="bucketDetail"
                />
                <div class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-[#425b76]">
                    <span><strong class="text-[#33475b]">{{ data.total_in_ranges }}</strong> en tramos</span>
                    <span v-if="data.no_data"><strong class="text-[#33475b]">{{ data.no_data }}</strong> sin dato</span>
                    <span v-if="data.out_of_range"><strong class="text-[#33475b]">{{ data.out_of_range }}</strong> fuera de rango</span>
                </div>
            </template>

            <!-- client_progress: % de avance por cliente -->
            <template v-else-if="metric === 'client_progress'">
                <p class="mb-2 text-center text-[11px] text-[#98a4b3]">Haz clic en un tema para ver qué clientes están en ese estado.</p>
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-[#f5f8fa] p-3 text-center">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-[#425b76]">Avance promedio</p>
                        <p class="text-xl font-bold text-[#33475b]">{{ data.overall_avg_pct }}%</p>
                    </div>
                    <button
                        v-for="t in data.topic_averages"
                        :key="t.label"
                        type="button"
                        class="rounded-lg bg-[#f5f8fa] p-3 text-center transition hover:bg-white hover:shadow-sm"
                        :style="selectedTopic === t.label ? { boxShadow: '0 0 0 2px ' + t.color, backgroundColor: '#fff' } : {}"
                        @click="selectTopic(t.label)"
                    >
                        <p class="flex items-center justify-center gap-1 text-[11px] font-medium text-[#425b76]">
                            <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: t.color }" />
                            {{ t.label }}
                        </p>
                        <p class="text-xl font-bold text-[#33475b]">{{ t.avg_pct }}%</p>
                        <p class="text-[11px] text-[#98a4b3]">{{ t.clients }} clientes</p>
                    </button>
                    <button
                        v-if="completedCount > 0"
                        type="button"
                        class="rounded-lg bg-[#f5f8fa] p-3 text-center transition hover:bg-white hover:shadow-sm"
                        :style="selectedTopic === COMPLETED_KEY ? { boxShadow: '0 0 0 2px #2e7d32', backgroundColor: '#fff' } : {}"
                        @click="selectTopic(COMPLETED_KEY)"
                    >
                        <p class="flex items-center justify-center gap-1 text-[11px] font-medium text-[#425b76]">
                            <svg class="h-3 w-3 text-[#2e7d32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            Completado
                        </p>
                        <p class="text-xl font-bold text-[#33475b]">{{ completedCount }}</p>
                        <p class="text-[11px] text-[#98a4b3]">clientes</p>
                    </button>
                </div>
                <ReportChart
                    type="horizontalBar"
                    :labels="progressChart.labels"
                    :values="progressChart.values"
                    :colors="progressChart.colors"
                    :detail="progressChart.detail"
                    value-format="percent"
                    :height="Math.max(120, topClients.length * 26)"
                />
                <div class="mt-3 text-center">
                    <button
                        type="button"
                        class="text-xs font-medium text-[#1976d2] hover:underline"
                        @click="showTable = !showTable"
                    >
                        {{ showTable ? 'Ocultar' : 'Ver' }} detalle por cliente ({{ filteredRows.length }})
                    </button>
                </div>
                <div v-if="showTable">
                    <div v-if="activeFilterLabel" class="mt-3 flex items-center justify-between rounded-md bg-[#eef4f9] px-3 py-2 text-xs text-[#33475b]">
                        <span>Mostrando clientes en <strong>{{ activeFilterLabel }}</strong> ({{ filteredRows.length }})</span>
                        <button type="button" class="font-medium text-[#1976d2] hover:underline" @click="clearTopic">Ver todos</button>
                    </div>
                    <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border border-[#eef2f6]">
                        <table class="min-w-full divide-y divide-[#eef2f6] text-sm">
                            <thead class="sticky top-0 bg-[#f5f8fa]">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Cliente</th>
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Valor</th>
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Tema</th>
                                    <th class="px-3 py-2 text-left font-medium text-[#33475b]">Avance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#eef2f6] bg-white">
                                <tr v-for="r in filteredRows" :key="r.id" class="hover:bg-[#f5f8fa]/50">
                                    <td class="px-3 py-2">
                                        <button type="button" class="text-left font-medium text-[#1976d2] hover:underline" @click="emit('open-client', r)">{{ r.name || '—' }}</button>
                                    </td>
                                    <td class="px-3 py-2 text-[#425b76]">{{ r.value }}</td>
                                    <td class="px-3 py-2">
                                        <span v-if="isCompleted(r)" class="inline-flex items-center gap-1 rounded-full bg-[#e8f5e9] px-2 py-0.5 text-[11px] font-medium text-[#2e7d32]">Completado</span>
                                        <span v-else class="text-[#425b76]">{{ r.topic || '—' }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-[#eef2f6]">
                                                <div class="h-full rounded-full bg-[var(--color-primary)]" :style="{ width: r.overall_pct + '%' }" />
                                            </div>
                                            <span class="text-xs text-[#425b76]">{{ r.overall_pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!filteredRows.length">
                                    <td colspan="4" class="px-3 py-6 text-center text-[#98a4b3]">No hay clientes en este grupo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <!-- topic_progress: avance por temas (campos compañeros) -->
            <template v-else-if="metric === 'topic_progress'">
                <!-- Vista distribución (dona / barras) -->
                <template v-if="chartType === 'doughnut' || chartType === 'bar'">
                    <ReportChart
                        :type="chartType"
                        :labels="tpDist.labels"
                        :values="tpDist.values"
                        :colors="tpDist.colors"
                    />
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-[#425b76]">
                        <span v-if="data.completed_count"><strong class="text-[#33475b]">{{ data.completed_count }}</strong> completados</span>
                        <span v-if="data.not_started_count"><strong class="text-[#33475b]">{{ data.not_started_count }}</strong> sin iniciar</span>
                        <span><strong class="text-[#33475b]">{{ data.total }}</strong> clientes</span>
                    </div>
                </template>

                <!-- Vista avance por cliente -->
                <template v-else>
                    <p class="mb-2 text-center text-[11px] text-[#98a4b3]">Haz clic en un tema para ver qué clientes están en ese estado.</p>
                    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <button
                            v-for="t in data.topic_summary"
                            :key="t.label"
                            type="button"
                            class="rounded-lg bg-[#f5f8fa] p-3 text-center transition hover:bg-white hover:shadow-sm"
                            :style="tpSelected === t.label ? { boxShadow: '0 0 0 2px ' + t.color, backgroundColor: '#fff' } : {}"
                            @click="tpSelect(t.label)"
                        >
                            <p class="flex items-center justify-center gap-1 text-[11px] font-medium text-[#425b76]">
                                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: t.color }" />
                                {{ t.label }}
                            </p>
                            <p class="text-xl font-bold text-[#33475b]">{{ t.clients }}</p>
                            <p class="text-[11px] text-[#98a4b3]">prom. {{ t.avg_pct }}%</p>
                        </button>
                        <button
                            v-if="data.completed_count > 0"
                            type="button"
                            class="rounded-lg bg-[#f5f8fa] p-3 text-center transition hover:bg-white hover:shadow-sm"
                            :style="tpSelected === TP_COMPLETED ? { boxShadow: '0 0 0 2px #2e7d32', backgroundColor: '#fff' } : {}"
                            @click="tpSelect(TP_COMPLETED)"
                        >
                            <p class="flex items-center justify-center gap-1 text-[11px] font-medium text-[#425b76]">
                                <svg class="h-3 w-3 text-[#2e7d32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                Completado
                            </p>
                            <p class="text-xl font-bold text-[#33475b]">{{ data.completed_count }}</p>
                            <p class="text-[11px] text-[#98a4b3]">clientes</p>
                        </button>
                        <button
                            v-if="data.not_started_count > 0"
                            type="button"
                            class="rounded-lg bg-[#f5f8fa] p-3 text-center transition hover:bg-white hover:shadow-sm"
                            :style="tpSelected === TP_NOT_STARTED ? { boxShadow: '0 0 0 2px #9e9e9e', backgroundColor: '#fff' } : {}"
                            @click="tpSelect(TP_NOT_STARTED)"
                        >
                            <p class="text-[11px] font-medium text-[#425b76]">Sin iniciar</p>
                            <p class="text-xl font-bold text-[#33475b]">{{ data.not_started_count }}</p>
                            <p class="text-[11px] text-[#98a4b3]">clientes</p>
                        </button>
                    </div>
                    <ReportChart
                        type="horizontalBar"
                        :labels="tpChart.labels"
                        :values="tpChart.values"
                        :colors="tpChart.colors"
                        :detail="tpChart.detail"
                        value-format="percent"
                        :height="Math.max(120, tpTop.length * 26)"
                    />
                    <div class="mt-3 text-center">
                        <button
                            type="button"
                            class="text-xs font-medium text-[#1976d2] hover:underline"
                            @click="showTable = !showTable"
                        >
                            {{ showTable ? 'Ocultar' : 'Ver' }} detalle por cliente ({{ tpFilteredRows.length }})
                        </button>
                    </div>
                    <div v-if="showTable">
                        <div v-if="tpActiveLabel" class="mt-3 flex items-center justify-between rounded-md bg-[#eef4f9] px-3 py-2 text-xs text-[#33475b]">
                            <span>Mostrando <strong>{{ tpActiveLabel }}</strong> ({{ tpFilteredRows.length }})</span>
                            <button type="button" class="font-medium text-[#1976d2] hover:underline" @click="tpClear">Ver todos</button>
                        </div>
                        <div class="mt-3 max-h-72 overflow-y-auto rounded-lg border border-[#eef2f6]">
                            <table class="min-w-full divide-y divide-[#eef2f6] text-sm">
                                <thead class="sticky top-0 bg-[#f5f8fa]">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-[#33475b]">Cliente</th>
                                        <th class="px-3 py-2 text-left font-medium text-[#33475b]">Estado</th>
                                        <th class="px-3 py-2 text-left font-medium text-[#33475b]">Avance del tema</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#eef2f6] bg-white">
                                    <tr v-for="r in tpFilteredRows" :key="r.id" class="hover:bg-[#f5f8fa]/50">
                                        <td class="px-3 py-2">
                                        <button type="button" class="text-left font-medium text-[#1976d2] hover:underline" @click="emit('open-client', r)">{{ r.name || '—' }}</button>
                                    </td>
                                        <td class="px-3 py-2">
                                            <span
                                                v-if="r.status === 'completed'"
                                                class="inline-flex items-center gap-1 rounded-full bg-[#e8f5e9] px-2 py-0.5 text-[11px] font-medium text-[#2e7d32]"
                                            >Completado</span>
                                            <span
                                                v-else-if="r.status === 'not_started'"
                                                class="inline-flex items-center gap-1 rounded-full bg-[#f0f0f0] px-2 py-0.5 text-[11px] font-medium text-[#757575]"
                                            >Sin iniciar</span>
                                            <span v-else class="inline-flex items-center gap-1 text-[#425b76]">
                                                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: tpColor(r) }" />
                                                {{ r.topic }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div v-if="r.status !== 'not_started'" class="flex items-center gap-2">
                                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-[#eef2f6]">
                                                    <div class="h-full rounded-full" :style="{ width: (r.topic_pct ?? 0) + '%', backgroundColor: tpColor(r) }" />
                                                </div>
                                                <span class="text-xs text-[#425b76]">{{ r.topic_pct ?? 0 }}%</span>
                                            </div>
                                            <span v-else class="text-xs text-[#98a4b3]">—</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!tpFilteredRows.length">
                                        <td colspan="3" class="px-3 py-6 text-center text-[#98a4b3]">No hay clientes en este grupo.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </template>

            <!-- value_counts: distribución de valores -->
            <template v-else-if="metric === 'value_counts'">
                <ReportChart
                    :type="chartType === 'doughnut' ? 'doughnut' : 'bar'"
                    :labels="data.labels"
                    :values="data.values"
                    :colors="data.colors"
                />
                <div class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-[#425b76]">
                    <span><strong class="text-[#33475b]">{{ data.total }}</strong> respuestas</span>
                    <span v-if="data.average != null">Promedio: <strong class="text-[#33475b]">{{ data.average }}</strong></span>
                </div>
            </template>

            <!-- calls: métricas de llamadas -->
            <template v-else-if="metric === 'calls'">
                <div class="grid gap-3" :class="callMetrics.includes('total') && callMetrics.includes('avg') ? 'sm:grid-cols-2' : 'grid-cols-1'">
                    <div v-if="callMetrics.includes('total')" class="rounded-lg bg-[#fff8e1] p-4 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-[#8a6d00]">Total de llamadas</p>
                        <p class="text-3xl font-bold text-[#f9a825]">{{ data.total_calls?.toLocaleString() }}</p>
                    </div>
                    <div v-if="callMetrics.includes('avg')" class="rounded-lg bg-[#e3f2fd] p-4 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-[#0d5aa7]">Promedio por cliente</p>
                        <p class="text-3xl font-bold text-[#1976d2]">{{ data.avg_per_client }}</p>
                        <p class="text-[11px] text-[#425b76]">sobre {{ data.clients_with_phone }} clientes con teléfono</p>
                    </div>
                </div>
                <div v-if="callMetrics.includes('distribution')" class="mt-4">
                    <p class="mb-1 text-xs font-medium text-[#425b76]">Distribución de llamadas por cliente</p>
                    <ReportChart
                        type="bar"
                        :labels="data.distribution.labels"
                        :values="data.distribution.values"
                        :colors="data.distribution.colors"
                        :height="220"
                    />
                </div>
            </template>
        </div>
    </div>
</template>
