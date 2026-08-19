<script setup>
import { ref, computed, watch } from 'vue';
import { toast } from 'vue3-toastify';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    agent: { type: [Object, Number, String], required: true },
    client: { type: Object, default: null },
});

const emit = defineEmits(['close', 'count-changed']);

const loading = ref(false);
const alerts = ref([]);
const categories = ref([]);

const agentId = computed(() => (typeof props.agent === 'object' ? props.agent?.id : props.agent));
const clientId = computed(() => props.client?.id ?? null);
const clientName = computed(() =>
    props.client ? `${props.client.name ?? ''} ${props.client.lastname ?? ''}`.trim() : 'Cliente'
);

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

async function fetchAlerts() {
    if (!clientId.value) return;
    loading.value = true;
    alerts.value = [];
    try {
        const { data } = await axios.get(route('agents.clients.call-alerts.index', [agentId.value, clientId.value]));
        alerts.value = data.call_alerts || [];
        categories.value = data.alert_categories || [];
    } catch (e) {
        toast.error('No se pudieron cargar las alertas de llamada.');
    } finally {
        loading.value = false;
    }
}

watch(() => [props.show, clientId.value], ([show]) => {
    if (show && clientId.value) fetchAlerts();
});

async function updateCategory(alert, event) {
    const raw = event.target.value;
    const categoriaId = raw === '' ? null : Number(raw);
    try {
        const { data } = await axios.patch(
            route('agents.clients.call-alerts.update', [agentId.value, clientId.value, alert.id]),
            { alerta_categoria_id: categoriaId },
        );
        alerts.value = data.call_alerts || [];
    } catch (e) {
        toast.error('No se pudo actualizar la categoría.');
    }
}

async function deleteAlert(alert) {
    if (!confirm('¿Eliminar esta alerta de llamada?')) return;
    try {
        const { data } = await axios.delete(route('agents.clients.call-alerts.destroy', [agentId.value, clientId.value, alert.id]));
        alerts.value = data.call_alerts || [];
        emit('count-changed', { clientId: clientId.value, count: alerts.value.length });
    } catch (e) {
        toast.error('No se pudo eliminar la alerta.');
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:p-6" @click.self="emit('close')">
            <div class="my-4 w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-[#e3e8ee] bg-[#f9fbfc] px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-[#33475b]">Alertas de llamada</h2>
                        <p class="mt-0.5 text-xs text-[#425b76]">{{ clientName }}</p>
                    </div>
                    <button type="button" class="rounded p-1.5 text-[#425b76] hover:bg-[#eef2f6]" @click="emit('close')">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="max-h-[75vh] overflow-y-auto px-6 py-5">
                    <div v-if="loading" class="py-10 text-center text-[#425b76]">Cargando alertas...</div>
                    <div v-else-if="!alerts.length" class="py-10 text-center text-sm text-[#98a4b3]">Este cliente no tiene alertas de llamada.</div>
                    <ul v-else class="divide-y divide-[#eef2f6] rounded-lg border border-[#eef2f6]">
                        <li v-for="a in alerts" :key="a.id" class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#425b76]">
                                        <span v-if="a.phone">📞 {{ a.phone }}</span>
                                        <span>Llamada #{{ a.numero_llamada ?? '—' }}</span>
                                        <span>Paso {{ a.paso_llamada ?? '—' }}</span>
                                        <span class="text-[#98a4b3]">{{ fmtDate(a.created_at) }}</span>
                                    </div>
                                    <p v-if="a.descripcion" class="mt-2 whitespace-pre-wrap text-sm text-[#33475b]">{{ a.descripcion }}</p>
                                </div>
                                <button type="button" class="shrink-0 rounded p-1 text-[#c2cddb] hover:bg-red-50 hover:text-red-500" title="Eliminar alerta" @click="deleteAlert(a)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <span
                                    v-if="a.categoria"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :style="{ backgroundColor: (a.categoria.color || '#eef2f6') + '22', color: a.categoria.color || '#425b76' }"
                                >
                                    <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: a.categoria.color || '#c2cddb' }" />
                                    {{ a.categoria.nombre }}
                                </span>
                                <select
                                    class="rounded-md border-[#e3e8ee] py-1 text-xs text-[#33475b] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                    :value="a.categoria ? a.categoria.id : ''"
                                    @change="updateCategory(a, $event)"
                                >
                                    <option value="">Sin categoría</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                                </select>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </Teleport>
</template>
