<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    agent: Object,
});

const loading = ref(false);
const data = ref({ month: '', total: 0, by_kind: [], by_model: [], trend: [] });
const month = ref('');

const kindColors = {
    message: 'bg-emerald-500',
    audio: 'bg-indigo-500',
    image: 'bg-amber-500',
    extraction: 'bg-sky-500',
};

async function load() {
    loading.value = true;
    try {
        const { data: res } = await axios.get(route('agents.ai-costs.index', props.agent), {
            params: month.value ? { month: month.value } : {},
        });
        data.value = res;
        month.value = res.month;
    } catch (e) {
        // silencioso
    } finally {
        loading.value = false;
    }
}

const maxTrend = computed(() => Math.max(0.0001, ...data.value.trend.map((t) => t.cost)));

function fmtUsd(n) {
    return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}
function fmtMonth(ym) {
    if (!ym) return '';
    const [y, m] = ym.split('-');
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return `${meses[parseInt(m, 10) - 1]} ${y}`;
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e3e8ee] px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-[#33475b]">Costos de IA</h3>
                    <p class="mt-1 text-sm text-[#425b76]">Costo estimado por mensaje, transcripción de audio, análisis de imagen y extracción. Valores aproximados en USD.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        v-model="month"
                        type="month"
                        class="rounded-md border-[#e3e8ee] text-sm text-[#33475b]"
                        @change="load"
                    />
                    <button type="button" class="text-sm font-medium text-emerald-600 hover:text-emerald-700" @click="load">Actualizar</button>
                </div>
            </div>

            <div class="p-6">
                <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>
                <template v-else>
                    <!-- Total del mes -->
                    <div class="mb-6 flex items-baseline gap-3">
                        <span class="text-3xl font-semibold text-[#33475b]">{{ fmtUsd(data.total) }}</span>
                        <span class="text-sm text-[#425b76]">total en {{ fmtMonth(data.month) }}</span>
                    </div>

                    <!-- Desglose por tipo -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="k in data.by_kind" :key="k.kind" class="rounded-lg border border-[#e3e8ee] p-4">
                            <div class="flex items-center gap-2">
                                <span :class="['h-2.5 w-2.5 rounded-full', kindColors[k.kind] || 'bg-gray-400']" />
                                <span class="text-sm font-medium text-[#33475b]">{{ k.label }}</span>
                            </div>
                            <p class="mt-2 text-xl font-semibold text-[#33475b]">{{ fmtUsd(k.cost) }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ k.count }} operación(es)<template v-if="k.kind !== 'audio'"> · {{ k.tokens.toLocaleString('es') }} tokens</template><template v-else> · {{ Math.round(k.seconds) }}s de audio</template>
                            </p>
                        </div>
                        <p v-if="!data.by_kind.length" class="text-sm text-gray-400 sm:col-span-2 lg:col-span-4">Sin costos registrados en este mes.</p>
                    </div>

                    <!-- Tendencia 6 meses -->
                    <div v-if="data.trend.length" class="mt-8">
                        <h4 class="mb-2 text-sm font-semibold text-[#33475b]">Últimos meses</h4>
                        <div class="flex items-end gap-3" style="height: 120px">
                            <div v-for="t in data.trend" :key="t.month" class="flex flex-1 flex-col items-center justify-end">
                                <span class="mb-1 text-[11px] text-[#425b76]">{{ fmtUsd(t.cost) }}</span>
                                <div
                                    class="w-full rounded-t bg-emerald-400"
                                    :style="{ height: Math.max(2, (t.cost / maxTrend) * 90) + 'px' }"
                                />
                                <span class="mt-1 text-[11px] text-gray-400">{{ fmtMonth(t.month) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Por modelo -->
                    <div v-if="data.by_model.length" class="mt-8">
                        <h4 class="mb-2 text-sm font-semibold text-[#33475b]">Por modelo</h4>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#eef2f6] text-left text-xs uppercase text-gray-400">
                                    <th class="py-2">Modelo</th>
                                    <th class="py-2 text-right">Operaciones</th>
                                    <th class="py-2 text-right">Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in data.by_model" :key="m.model" class="border-b border-[#f4f7fa]">
                                    <td class="py-2 font-mono text-[#33475b]">{{ m.model }}</td>
                                    <td class="py-2 text-right text-[#425b76]">{{ m.count }}</td>
                                    <td class="py-2 text-right font-medium text-[#33475b]">{{ fmtUsd(m.cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-6 text-xs text-gray-400">
                        Los costos son estimaciones basadas en los precios por modelo (configurables en <span class="font-mono">config/ai_pricing.php</span>) y pueden diferir de la factura real del proveedor.
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>
