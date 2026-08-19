<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    agent: { type: Object, required: true },
    widget: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const PALETTE = ['#2e7d32', '#1976d2', '#f9a825', '#c62828', '#6a1b9a', '#00838f'];

const METRICS = [
    {
        value: 'topic_progress',
        label: 'Avance por temas (campos compañeros)',
        help: 'Determina el tema de cada cliente con campos como fatiga y emociones (0..máx). Un campo en su máximo = tema completado; el cliente pasa al otro tema. El % es valor/máximo del tema actual.',
        source: 'custom_field',
        charts: ['progress', 'doughnut', 'bar'],
    },
    {
        value: 'range_buckets',
        label: 'Distribución / Embudo por tramos',
        help: 'Agrupa un campo numérico en tramos con etiqueta (ej. STEP 1–28 → Fatiga). Útil para campos numéricos simples.',
        source: 'custom_field',
        charts: ['doughnut', 'bar', 'funnel'],
    },
    {
        value: 'client_progress',
        label: 'Avance % por cliente (un campo)',
        help: 'Calcula el porcentaje de avance de cada cliente respecto a un máximo de un solo campo, y en qué tramo va.',
        source: 'custom_field',
        charts: ['bar'],
    },
    {
        value: 'value_counts',
        label: 'Distribución de valores',
        help: 'Cuenta cuántos clientes tienen cada valor de un campo (ej. calificación 1–5).',
        source: 'custom_field',
        charts: ['bar', 'doughnut'],
    },
    {
        value: 'calls',
        label: 'Llamadas',
        help: 'Total de llamadas, promedio por cliente y distribución (desde Supabase, por teléfono).',
        source: 'calls',
        charts: ['stat'],
    },
];

const CHART_LABELS = { doughnut: 'Dona', bar: 'Barras', funnel: 'Embudo', stat: 'Tarjetas', progress: 'Avance por cliente' };

const knownFields = computed(() => (props.agent?.client_fields || props.agent?.clientFields || []).map((f) => f.field_name));

const PALETTE_TOPICS = ['#2e7d32', '#1976d2', '#f9a825', '#6a1b9a', '#00838f'];

function emptyConfig() {
    return { max: null, ranges: [], values: [], topics: [], calls_metrics: ['total', 'avg', 'distribution'], width: 'half' };
}

const form = useForm({
    title: '',
    metric: 'topic_progress',
    source: 'custom_field',
    field_name: '',
    chart_type: 'progress',
    config: emptyConfig(),
});

const isEdit = computed(() => !!props.widget);
const currentMetric = computed(() => METRICS.find((m) => m.value === form.metric) || METRICS[0]);

function resetFromWidget() {
    if (props.widget) {
        const w = props.widget;
        form.title = w.title;
        form.metric = w.metric;
        form.source = w.source;
        form.field_name = w.field_name || '';
        form.chart_type = w.chart_type;
        form.config = {
            max: w.config?.max ?? null,
            ranges: (w.config?.ranges || []).map((r) => ({ ...r })),
            values: (w.config?.values || []).map((v) => ({ ...v })),
            topics: (w.config?.topics || []).map((t) => ({ ...t })),
            calls_metrics: w.config?.calls_metrics || ['total', 'avg', 'distribution'],
            width: w.config?.width || 'half',
        };
    } else {
        form.reset();
        form.clearErrors();
        form.config = emptyConfig();
    }
}

watch(() => props.show, (v) => { if (v) resetFromWidget(); });

function onMetricChange() {
    const m = currentMetric.value;
    form.source = m.source;
    if (!m.charts.includes(form.chart_type)) form.chart_type = m.charts[0];
    if (m.source === 'calls') form.field_name = '';
    if (m.value === 'range_buckets' && form.config.ranges.length === 0) addRange();
    if (m.value === 'topic_progress' && form.config.topics.length === 0) addTopic();
}

/* ---- Tramos ---- */
function addRange() {
    const i = form.config.ranges.length;
    form.config.ranges.push({ from: null, to: null, label: '', color: PALETTE[i % PALETTE.length] });
}
function removeRange(i) {
    form.config.ranges.splice(i, 1);
}

/* ---- Temas (topic_progress) ---- */
function addTopic() {
    const i = form.config.topics.length;
    form.config.topics.push({ label: '', field: '', max: 3, color: PALETTE_TOPICS[i % PALETTE_TOPICS.length] });
}
function removeTopic(i) {
    form.config.topics.splice(i, 1);
}

/* ---- Valores discretos ---- */
function addValue() {
    const i = form.config.values.length;
    form.config.values.push({ value: '', label: '', color: PALETTE[i % PALETTE.length] });
}
function removeValue(i) {
    form.config.values.splice(i, 1);
}

function toggleCallMetric(m) {
    const arr = form.config.calls_metrics;
    const idx = arr.indexOf(m);
    if (idx >= 0) arr.splice(idx, 1);
    else arr.push(m);
}

/* ---- Presets ---- */
function applyPreset(kind) {
    if (kind === 'avance') {
        form.title = 'Avance del proceso';
        form.metric = 'topic_progress';
        form.source = 'custom_field';
        form.field_name = '';
        form.chart_type = 'progress';
        form.config.width = 'full';
        form.config.topics = [
            { label: 'Fatiga', field: knownFields.value.find((f) => /fatiga/i.test(f)) || 'fatiga', max: 3, color: '#2e7d32' },
            { label: 'Emociones', field: knownFields.value.find((f) => /emoci/i.test(f)) || 'emociones', max: 3, color: '#1976d2' },
        ];
    } else if (kind === 'satisfaction') {
        form.title = 'Calificación de satisfacción';
        form.metric = 'value_counts';
        form.source = 'custom_field';
        form.field_name = knownFields.value.find((f) => /calific|satisf/i.test(f)) || 'calificacion_satisfaccion';
        form.chart_type = 'bar';
        form.config.values = [
            { value: '1', label: '1 · Muy malo', color: '#c62828' },
            { value: '2', label: '2 · Malo', color: '#ef6c00' },
            { value: '3', label: '3 · Regular', color: '#f9a825' },
            { value: '4', label: '4 · Bueno', color: '#43a047' },
            { value: '5', label: '5 · Excelente', color: '#2e7d32' },
        ];
    } else if (kind === 'calls') {
        form.title = 'Llamadas realizadas';
        form.metric = 'calls';
        form.source = 'calls';
        form.field_name = '';
        form.chart_type = 'stat';
        form.config.calls_metrics = ['total', 'avg', 'distribution'];
    }
}

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('close');
        },
    };
    if (isEdit.value) {
        form.put(route('agents.report-widgets.update', [props.agent.id, props.widget.id]), opts);
    } else {
        form.post(route('agents.report-widgets.store', props.agent.id), opts);
    }
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="max-h-[85vh] overflow-y-auto">
            <div class="border-b border-[#e3e8ee] px-6 py-4">
                <h2 class="text-lg font-semibold text-[#33475b]">
                    {{ isEdit ? 'Editar reporte' : 'Nuevo reporte personalizado' }}
                </h2>
                <p class="mt-0.5 text-sm text-[#425b76]">Define la regla y cómo quieres graficarla.</p>
            </div>

            <div class="space-y-5 px-6 py-5">
                <!-- Presets -->
                <div v-if="!isEdit">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-[#98a4b3]">Plantillas rápidas</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-full border border-[#e3e8ee] px-3 py-1 text-xs text-[#33475b] transition hover:border-[var(--color-primary)] hover:bg-[#f5f8fa]" @click="applyPreset('avance')">Avance por temas (Fatiga/Emociones)</button>
                        <button type="button" class="rounded-full border border-[#e3e8ee] px-3 py-1 text-xs text-[#33475b] transition hover:border-[var(--color-primary)] hover:bg-[#f5f8fa]" @click="applyPreset('satisfaction')">Satisfacción (1–5)</button>
                        <button type="button" class="rounded-full border border-[#e3e8ee] px-3 py-1 text-xs text-[#33475b] transition hover:border-[var(--color-primary)] hover:bg-[#f5f8fa]" @click="applyPreset('calls')">Llamadas</button>
                    </div>
                </div>

                <!-- Título -->
                <div>
                    <label class="text-sm font-medium text-[#33475b]">Título del reporte</label>
                    <input v-model="form.title" type="text" class="mt-1 w-full rounded-md border-[#e3e8ee] text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]" placeholder="ej. Avance del proceso" />
                    <InputError :message="form.errors.title" />
                </div>

                <!-- Tipo de reporte -->
                <div>
                    <label class="text-sm font-medium text-[#33475b]">Tipo de reporte</label>
                    <select v-model="form.metric" class="mt-1 w-full rounded-md border-[#e3e8ee] text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]" @change="onMetricChange">
                        <option v-for="m in METRICS" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                    <p class="mt-1 text-xs text-[#425b76]">{{ currentMetric.help }}</p>
                </div>

                <!-- Campo (para métricas de un solo campo) -->
                <div v-if="form.source === 'custom_field' && form.metric !== 'topic_progress'">
                    <label class="text-sm font-medium text-[#33475b]">Campo del cliente</label>
                    <input v-model="form.field_name" list="report-fields" type="text" class="mt-1 w-full rounded-md border-[#e3e8ee] text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]" placeholder="ej. STEP" />
                    <datalist id="report-fields">
                        <option v-for="f in knownFields" :key="f" :value="f" />
                    </datalist>
                    <InputError :message="form.errors.field_name" />
                    <p class="mt-1 text-xs text-[#425b76]">Nombre exacto del campo en los datos recolectados (custom_fields).</p>
                </div>

                <!-- Temas (topic_progress) -->
                <div v-if="form.metric === 'topic_progress'">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium text-[#33475b]">Temas</label>
                        <button type="button" class="text-xs font-medium text-[#1976d2] hover:underline" @click="addTopic">+ Agregar tema</button>
                    </div>
                    <p class="mb-2 text-xs text-[#425b76]">Cada tema tiene un campo (0..máx). Cuando el campo llega al máximo, el tema está completado y el cliente pasa al siguiente.</p>
                    <div class="space-y-2">
                        <div v-for="(t, i) in form.config.topics" :key="i" class="flex flex-wrap items-center gap-2 rounded-md border border-[#eef2f6] bg-[#f9fbfc] p-2">
                            <input v-model="t.label" type="text" placeholder="Tema (ej. Fatiga)" class="min-w-[7rem] flex-1 rounded border-[#e3e8ee] text-sm" />
                            <input v-model="t.field" :list="'report-fields'" type="text" placeholder="Campo (ej. fatiga)" class="min-w-[7rem] flex-1 rounded border-[#e3e8ee] text-sm" />
                            <label class="flex items-center gap-1 text-xs text-[#425b76]">máx
                                <input v-model.number="t.max" type="number" min="1" class="w-16 rounded border-[#e3e8ee] text-sm" />
                            </label>
                            <input v-model="t.color" type="color" class="h-8 w-10 cursor-pointer rounded border-[#e3e8ee]" />
                            <button type="button" class="rounded p-1 text-red-500 hover:bg-red-50" @click="removeTopic(i)">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <p v-if="!form.config.topics.length" class="text-xs text-[#98a4b3]">Agrega al menos un tema (ej. Fatiga → campo "fatiga", máx 3).</p>
                    </div>
                    <datalist id="report-fields">
                        <option v-for="f in knownFields" :key="f" :value="f" />
                    </datalist>
                </div>

                <!-- Tramos (range_buckets y client_progress) -->
                <div v-if="form.metric === 'range_buckets' || form.metric === 'client_progress'">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium text-[#33475b]">Tramos (temas)</label>
                        <button type="button" class="text-xs font-medium text-[#1976d2] hover:underline" @click="addRange">+ Agregar tramo</button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(r, i) in form.config.ranges" :key="i" class="flex flex-wrap items-center gap-2 rounded-md border border-[#eef2f6] bg-[#f9fbfc] p-2">
                            <input v-model.number="r.from" type="number" placeholder="Desde" class="w-20 rounded border-[#e3e8ee] text-sm" />
                            <span class="text-[#98a4b3]">–</span>
                            <input v-model.number="r.to" type="number" placeholder="Hasta" class="w-20 rounded border-[#e3e8ee] text-sm" />
                            <input v-model="r.label" type="text" placeholder="Etiqueta (ej. Fatiga)" class="min-w-[8rem] flex-1 rounded border-[#e3e8ee] text-sm" />
                            <input v-model="r.color" type="color" class="h-8 w-10 cursor-pointer rounded border-[#e3e8ee]" />
                            <button type="button" class="rounded p-1 text-red-500 hover:bg-red-50" @click="removeRange(i)">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <p v-if="!form.config.ranges.length" class="text-xs text-[#98a4b3]">Agrega al menos un tramo (ej. 1–28 → Fatiga).</p>
                    </div>
                </div>

                <!-- Máximo (client_progress) -->
                <div v-if="form.metric === 'client_progress'">
                    <label class="text-sm font-medium text-[#33475b]">Valor máximo (100% de avance)</label>
                    <input v-model.number="form.config.max" type="number" class="mt-1 w-40 rounded-md border-[#e3e8ee] text-sm shadow-sm" placeholder="ej. 49" />
                    <p class="mt-1 text-xs text-[#425b76]">Si lo dejas vacío, se usa el tope del último tramo.</p>
                </div>

                <!-- Valores discretos (value_counts) -->
                <div v-if="form.metric === 'value_counts'">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium text-[#33475b]">Valores (opcional)</label>
                        <button type="button" class="text-xs font-medium text-[#1976d2] hover:underline" @click="addValue">+ Agregar valor</button>
                    </div>
                    <div class="space-y-2">
                        <div v-for="(v, i) in form.config.values" :key="i" class="flex flex-wrap items-center gap-2 rounded-md border border-[#eef2f6] bg-[#f9fbfc] p-2">
                            <input v-model="v.value" type="text" placeholder="Valor (ej. 5)" class="w-24 rounded border-[#e3e8ee] text-sm" />
                            <input v-model="v.label" type="text" placeholder="Etiqueta (ej. Excelente)" class="min-w-[8rem] flex-1 rounded border-[#e3e8ee] text-sm" />
                            <input v-model="v.color" type="color" class="h-8 w-10 cursor-pointer rounded border-[#e3e8ee]" />
                            <button type="button" class="rounded p-1 text-red-500 hover:bg-red-50" @click="removeValue(i)">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <p class="text-xs text-[#98a4b3]">Déjalo vacío para detectar los valores automáticamente.</p>
                    </div>
                </div>

                <!-- Métricas de llamadas -->
                <div v-if="form.metric === 'calls'">
                    <label class="text-sm font-medium text-[#33475b]">Métricas a mostrar</label>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <label class="flex items-center gap-2 text-sm text-[#33475b]">
                            <input type="checkbox" :checked="form.config.calls_metrics.includes('total')" class="rounded border-[#e3e8ee]" @change="toggleCallMetric('total')" /> Total de llamadas
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[#33475b]">
                            <input type="checkbox" :checked="form.config.calls_metrics.includes('avg')" class="rounded border-[#e3e8ee]" @change="toggleCallMetric('avg')" /> Promedio por cliente
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[#33475b]">
                            <input type="checkbox" :checked="form.config.calls_metrics.includes('distribution')" class="rounded border-[#e3e8ee]" @change="toggleCallMetric('distribution')" /> Distribución
                        </label>
                    </div>
                </div>

                <!-- Tipo de gráfica -->
                <div v-if="currentMetric.charts.length > 1">
                    <label class="text-sm font-medium text-[#33475b]">Tipo de gráfica</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <button
                            v-for="c in currentMetric.charts"
                            :key="c"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition"
                            :class="form.chart_type === c ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-primary-foreground)]' : 'border-[#e3e8ee] text-[#33475b] hover:bg-[#f5f8fa]'"
                            @click="form.chart_type = c"
                        >
                            {{ CHART_LABELS[c] }}
                        </button>
                    </div>
                </div>

                <!-- Ancho del reporte -->
                <div>
                    <label class="text-sm font-medium text-[#33475b]">Ancho en el panel</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition"
                            :class="form.config.width !== 'full' ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-primary-foreground)]' : 'border-[#e3e8ee] text-[#33475b] hover:bg-[#f5f8fa]'"
                            @click="form.config.width = 'half'"
                        >
                            Media (50%)
                        </button>
                        <button
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition"
                            :class="form.config.width === 'full' ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-[var(--color-primary-foreground)]' : 'border-[#e3e8ee] text-[#33475b] hover:bg-[#f5f8fa]'"
                            @click="form.config.width = 'full'"
                        >
                            Completa (100%)
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-[#425b76]">Usa "Completa" para reportes con tabla o mucha información.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-[#e3e8ee] bg-[#f9fbfc] px-6 py-4">
                <button type="button" class="text-sm text-[#425b76] hover:text-[#33475b]" @click="emit('close')">Cancelar</button>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-md bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary-foreground)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-50"
                    @click="submit"
                >
                    {{ form.processing ? 'Guardando...' : (isEdit ? 'Guardar cambios' : 'Crear reporte') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
