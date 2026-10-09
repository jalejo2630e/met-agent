<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    agent: Object,
});

const loading = ref(false);
const data = ref({ from: '', to: '', sedes: [], daily: [] });
const from = ref('');
const to = ref('');

const sedeColors = {
    bogota: { bar: 'bg-sky-500', dot: 'bg-sky-500' },
    chia: { bar: 'bg-emerald-500', dot: 'bg-emerald-500' },
};
const fallbackColor = { bar: 'bg-gray-400', dot: 'bg-gray-400' };
const colorOf = (sede) => sedeColors[sede] || fallbackColor;

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (from.value) params.from = from.value;
        if (to.value) params.to = to.value;
        const { data: res } = await axios.get(route('agents.sede-metrics.index', props.agent), { params });
        data.value = res;
        from.value = res.from;
        to.value = res.to;
    } catch (e) {
        // silencioso
    } finally {
        loading.value = false;
    }
}

// Días del rango con el total de mensajes entrantes por sede.
const days = computed(() => {
    const map = new Map();
    data.value.daily.forEach((r) => {
        if (!map.has(r.day)) map.set(r.day, {});
        map.get(r.day)[r.sede ?? 'none'] = r.total;
    });
    return [...map.entries()].map(([day, bySede]) => ({ day, bySede }));
});
const maxDay = computed(() => Math.max(1, ...days.value.map((d) => Object.values(d.bySede).reduce((a, b) => a + b, 0))));

function fmtDay(d) {
    const [, m, day] = d.split('-');
    return `${day}/${m}`;
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e3e8ee] px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-[#33475b]">Interacciones por sede</h3>
                    <p class="mt-1 text-sm text-[#425b76]">Personas y mensajes que llegan a cada sede (Bogotá y Chía), según el número de WhatsApp que los recibe.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input v-model="from" type="date" class="rounded-md border-[#e3e8ee] text-sm text-[#33475b]" @change="load" />
                    <span class="text-sm text-gray-400">a</span>
                    <input v-model="to" type="date" class="rounded-md border-[#e3e8ee] text-sm text-[#33475b]" @change="load" />
                    <button type="button" class="text-sm font-medium text-emerald-600 hover:text-emerald-700" @click="load">Actualizar</button>
                </div>
            </div>

            <div class="p-6">
                <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>
                <template v-else>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-for="s in data.sedes" :key="s.sede ?? 'none'" class="rounded-lg border border-[#e3e8ee] p-4">
                            <div class="flex items-center gap-2">
                                <span :class="['h-2.5 w-2.5 rounded-full', colorOf(s.sede).dot]" />
                                <span class="text-base font-semibold text-[#33475b]">{{ s.label }}</span>
                            </div>
                            <p class="mt-3 text-3xl font-semibold text-[#33475b]">{{ s.unique_contacts.toLocaleString('es') }}</p>
                            <p class="text-xs text-gray-400">personas que escribieron</p>

                            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <dt class="text-[#425b76]">Mensajes recibidos</dt>
                                <dd class="text-right font-medium text-[#33475b]">{{ s.inbound.toLocaleString('es') }}</dd>
                                <dt class="text-[#425b76]">Mensajes enviados</dt>
                                <dd class="text-right font-medium text-[#33475b]">{{ s.outbound.toLocaleString('es') }}</dd>
                                <dt class="text-[#425b76]">Personas nuevas</dt>
                                <dd class="text-right font-medium text-[#33475b]">{{ s.new_contacts.toLocaleString('es') }}</dd>
                                <dt class="text-[#425b76]">Personas recurrentes</dt>
                                <dd class="text-right font-medium text-[#33475b]">{{ s.returning_contacts.toLocaleString('es') }}</dd>
                                <dt class="text-[#425b76]">Mensajes por persona</dt>
                                <dd class="text-right font-medium text-[#33475b]">{{ s.avg_inbound_per_contact }}</dd>
                            </dl>
                        </div>
                        <p v-if="!data.sedes.length" class="text-sm text-gray-400 md:col-span-2">Sin mensajes en este rango.</p>
                    </div>

                    <div v-if="days.length" class="mt-8">
                        <div class="mb-2 flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-[#33475b]">Mensajes recibidos por día</h4>
                            <div class="flex items-center gap-3 text-xs text-[#425b76]">
                                <span v-for="s in data.sedes" :key="s.sede ?? 'none'" class="flex items-center gap-1">
                                    <span :class="['h-2 w-2 rounded-full', colorOf(s.sede).dot]" /> {{ s.label }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-end gap-1 overflow-x-auto" style="height: 140px">
                            <div v-for="d in days" :key="d.day" class="flex min-w-[18px] flex-1 flex-col items-center justify-end" :title="d.day">
                                <div class="flex w-full flex-col-reverse overflow-hidden rounded-t">
                                    <div
                                        v-for="(total, sede) in d.bySede"
                                        :key="sede"
                                        :class="colorOf(sede === 'none' ? null : sede).bar"
                                        :style="{ height: Math.max(2, (total / maxDay) * 100) + 'px' }"
                                        :title="`${sede}: ${total}`"
                                    />
                                </div>
                                <span class="mt-1 text-[10px] text-gray-400">{{ fmtDay(d.day) }}</span>
                            </div>
                        </div>
                    </div>

                    <p class="mt-6 text-xs text-gray-400">
                        La sede se asigna por el número de WhatsApp receptor (<span class="font-mono">TWILIO_SEDE_BOGOTA_NUMBER</span> y <span class="font-mono">TWILIO_SEDE_CHIA_NUMBER</span>). Los mensajes anteriores a esta función aparecen como «Sin sede».
                    </p>
                </template>
            </div>
        </div>
    </div>
</template>
