<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
    endpointLogs: Array,
    contactLogs: { type: Array, default: () => [] },
    queueRuns: { type: Array, default: () => [] },
});

const expandedId = ref(null);

const filterStatus = ref('all'); // all, success, error

const filteredLogs = computed(() => {
    if (!props.endpointLogs) return [];
    if (filterStatus.value === 'success') return props.endpointLogs.filter(l => l.success);
    if (filterStatus.value === 'error') return props.endpointLogs.filter(l => !l.success);
    return props.endpointLogs;
});

const toggleExpand = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('es');
}

function truncate(str, len = 80) {
    if (!str) return '';
    const s = typeof str === 'object' ? JSON.stringify(str) : String(str);
    return s.length > len ? s.slice(0, len) + '...' : s;
}

const channelLabel = (ch) => ch === 'whatsapp' ? 'WhatsApp' : ch === 'call' ? 'Llamada' : ch;
const typeLabel = (t) => t === 'whatsapp' ? 'WhatsApp' : t === 'call' ? 'Llamada' : t;
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900">Logs de ejecución</h3>
            <p class="mt-1 text-sm text-[#425b76]">
                Endpoints precargados, contactos WhatsApp/Llamadas registrados y ejecuciones de colas programadas (cron).
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <select
                    v-model="filterStatus"
                    class="rounded-md border-[#e3e8ee] text-sm"
                >
                    <option value="all">Todos</option>
                    <option value="success">Exitosos</option>
                    <option value="error">Con error</option>
                </select>
                <SecondaryButton @click="router.reload()">Actualizar</SecondaryButton>
            </div>

            <div class="mt-4">
                <div v-if="!filteredLogs.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-12 text-center text-[#425b76]">
                    No hay logs de ejecución
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="log in filteredLogs"
                        :key="log.id"
                        class="rounded-lg border"
                        :class="log.success ? 'border-green-200 bg-green-50/50' : 'border-red-200 bg-red-50/50'"
                    >
                        <div
                            class="flex cursor-pointer items-center justify-between px-4 py-3"
                            @click="toggleExpand(log.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    :class="['h-2 w-2 rounded-full', log.success ? 'bg-green-500' : 'bg-red-500']"
                                />
                                <span class="text-sm font-medium">
                                    {{ log.endpoint?.name || 'Endpoint' }}
                                </span>
                                <span class="text-xs text-[#425b76]">
                                    {{ log.request_method }} — {{ formatDate(log.created_at) }}
                                </span>
                                <span
                                    v-if="log.response_status"
                                    :class="['rounded px-1.5 py-0.5 text-xs font-mono', log.success ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800']"
                                >
                                    {{ log.response_status }}
                                </span>
                                <span v-if="log.error_message" class="max-w-xs truncate text-xs text-red-600">
                                    {{ log.error_message }}
                                </span>
                            </div>
                            <svg
                                :class="['h-4 w-4 text-gray-400 transition-transform', expandedId === log.id && 'rotate-180']"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <div
                            v-show="expandedId === log.id"
                            class="border-t px-4 py-3 text-xs"
                            :class="log.success ? 'border-green-200' : 'border-red-200'"
                        >
                            <div class="grid gap-2 md:grid-cols-2">
                                <div v-if="log.request_url">
                                    <span class="font-medium text-gray-600">URL:</span>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/80 p-2">{{ log.request_url }}</pre>
                                </div>
                                <div v-if="log.error_message">
                                    <span class="font-medium text-red-600">Error:</span>
                                    <pre class="mt-1 overflow-x-auto rounded bg-white/80 p-2 text-red-700">{{ log.error_message }}</pre>
                                </div>
                                <div v-if="log.request_body" class="md:col-span-2">
                                    <span class="font-medium text-gray-600">Request body:</span>
                                    <pre class="mt-1 max-h-40 overflow-auto rounded bg-white/80 p-2">{{ truncate(log.request_body, 2000) }}</pre>
                                </div>
                                <div v-if="log.response_body" class="md:col-span-2">
                                    <span class="font-medium text-gray-600">Response:</span>
                                    <pre class="mt-1 max-h-64 overflow-auto rounded bg-white/80 p-2">{{ typeof log.response_body === 'object' ? JSON.stringify(log.response_body, null, 2) : log.response_body }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contactos WhatsApp / Llamadas -->
            <div class="mt-8">
                <h4 class="text-sm font-medium text-gray-700">Contactos WhatsApp / Llamadas</h4>
                <p class="mt-1 text-xs text-[#425b76]">Registros de contacto con clientes (tu integración debe escribir en ClientContactLog para que aparezcan aquí).</p>
                <div class="mt-3">
                    <div v-if="!contactLogs?.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-8 text-center text-sm text-[#425b76]">
                        No hay registros de contacto
                    </div>
                    <div v-else class="space-y-1.5">
                        <div
                            v-for="log in contactLogs"
                            :key="log.id"
                            class="flex items-center gap-3 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa]/50 px-3 py-2 text-sm"
                        >
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="log.channel === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                                {{ channelLabel(log.channel) }}
                            </span>
                            <span class="text-[#33475b]">{{ log.client?.name }} {{ log.client?.lastname }}</span>
                            <span class="text-[#425b76]">{{ log.client?.phone || '—' }}</span>
                            <span class="ml-auto text-xs text-[#425b76]">{{ formatDate(log.contacted_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ejecuciones de colas programadas (cron) -->
            <div class="mt-8">
                <h4 class="text-sm font-medium text-gray-700">Ejecuciones de colas programadas (cron)</h4>
                <p class="mt-1 text-xs text-[#425b76]">Últimas colas de mensajes/llamadas ejecutadas por el cron (contact-queues:process).</p>
                <div class="mt-3">
                    <div v-if="!queueRuns?.length" class="rounded-lg border border-dashed border-[#e3e8ee] py-8 text-center text-sm text-[#425b76]">
                        No hay ejecuciones recientes
                    </div>
                    <div v-else class="space-y-1.5">
                        <div
                            v-for="run in queueRuns"
                            :key="run.id"
                            class="flex items-center gap-3 rounded-lg border border-[#e3e8ee] bg-white px-3 py-2 text-sm"
                        >
                            <span class="rounded px-1.5 py-0.5 text-xs font-medium" :class="run.type === 'whatsapp' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                                {{ typeLabel(run.type) }}
                            </span>
                            <span class="rounded px-1.5 py-0.5 text-xs" :class="run.status === 'completed' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800'">
                                {{ run.status }}
                            </span>
                            <span class="text-[#425b76]">{{ run.processed_count }}/{{ run.total }} procesados</span>
                            <span v-if="run.failed_count" class="text-xs text-red-600">{{ run.failed_count }} fallidos</span>
                            <span class="ml-auto text-xs text-[#425b76]">{{ formatDate(run.last_run_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
