<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue3-toastify';

const props = defineProps({
    agent: Object,
    clients: { type: Object, default: () => ({ data: [] }) },
});

const requests = ref([]);
const loading = ref(true);
const statusFilter = ref('pending');
const serverTime = ref(null);
const serverTimezone = ref(null);
const bogotaTime = ref(null);
const showCreateModal = ref(false);
const creating = ref(false);

const form = ref({
    client_id: '',
    scheduled_date: '',
    scheduled_time: '',
    channel: 'call',
    notes: '',
});

const clientsList = ref([]);

const channelLabels = { call: 'Llamada', whatsapp: 'WhatsApp' };
const statusLabels = { pending: 'Pendiente', completed: 'Completado', cancelled: 'Cancelado' };
const showApiDocs = ref(false);

const apiCallbackUrl = computed(() => {
    const base = typeof window !== 'undefined' ? window.location.origin : '';
    return `${base}/api/agents/${props.agent?.id}/callback-requests`;
});

async function loadRequests() {
    loading.value = true;
    try {
        const { data } = await axios.get(route('agents.callback-requests.index', props.agent), {
            params: { status: statusFilter.value },
        });
        requests.value = data.callback_requests || [];
        serverTime.value = data.server_time ?? null;
        serverTimezone.value = data.server_timezone ?? null;
        bogotaTime.value = data.bogota_time ?? null;
    } catch {
        requests.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadRequests();
    clientsList.value = props.clients?.data || [];
});

function loadClients() {
    clientsList.value = props.clients?.data || [];
}

function openCreateModal() {
    form.value = { client_id: '', scheduled_date: '', scheduled_time: '', channel: 'call', notes: '' };
    loadClients();
    showCreateModal.value = true;
}

async function submitCreate() {
    if (!form.value.client_id || !form.value.scheduled_date) {
        toast.error('Selecciona cliente y fecha.');
        return;
    }
    creating.value = true;
    try {
        const { data } = await axios.post(route('agents.callback-requests.store', props.agent), form.value);
        if (data.success) {
            toast.success(data.message);
            showCreateModal.value = false;
            loadRequests();
        } else {
            toast.error(data.message || 'Error al crear.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al crear la solicitud.');
    } finally {
        creating.value = false;
    }
}

async function cancelRequest(req) {
    if (!confirm('¿Cancelar esta solicitud de callback?')) return;
    try {
        const { data } = await axios.post(route('agents.callback-requests.cancel', [props.agent, req.id]));
        if (data.success) {
            toast.success(data.message);
            loadRequests();
        } else {
            toast.error(data.message || 'Error.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'Error al cancelar.');
    }
}

function formatDate(d) {
    if (!d) return '-';
    const str = typeof d === 'string' ? d : (d.toISOString?.()?.split('T')[0] ?? '');
    return str || '-';
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
        <div class="p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Callbacks programados</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Cuando un usuario solicita que lo llamen en una fecha específica, crea aquí la tarea. Se ejecutará ese día a la hora que indiques y quedará registrada en el cliente.
                    </p>
                    <p v-if="serverTime" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#425b76]">
                        <span>
                            Hora del servidor: <strong class="font-mono">{{ serverTime }}</strong>
                            <span v-if="serverTimezone"> ({{ serverTimezone }})</span>
                        </span>
                        <span v-if="bogotaTime">
                            Bogotá: <strong class="font-mono">{{ bogotaTime }}</strong>
                        </span>
                    </p>
                </div>
                <PrimaryButton @click="openCreateModal">
                    + Programar callback
                </PrimaryButton>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    v-for="s in ['pending', 'completed', 'cancelled']"
                    :key="s"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-sm font-medium',
                        statusFilter === s
                            ? 'bg-[var(--color-primary)] text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                    @click="statusFilter = s; loadRequests()"
                >
                    {{ statusLabels[s] }}
                </button>
            </div>

            <div v-if="loading" class="mt-6 text-center text-gray-500">Cargando...</div>
            <div v-else-if="!requests.length" class="mt-6 rounded-lg border border-dashed border-[#e3e8ee] bg-gray-50 p-8 text-center text-gray-500">
                No hay solicitudes {{ statusFilter === 'pending' ? 'pendientes' : statusFilter === 'completed' ? 'completadas' : 'canceladas' }}.
            </div>
            <div v-else class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-[#f5f8fa]">
                        <tr>
                            <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Cliente</th>
                            <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Fecha</th>
                            <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Hora</th>
                            <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Canal</th>
                            <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Notas</th>
                            <th class="border-b border-[#e3e8ee] px-3 py-2 text-left font-medium text-[#33475b]">Estado</th>
                            <th v-if="statusFilter === 'pending'" class="border-b border-[#e3e8ee] px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in requests" :key="r.id" class="hover:bg-gray-50">
                            <td class="border-b border-[#e3e8ee] px-3 py-2">
                                <span class="font-medium">{{ r.client?.name }} {{ r.client?.lastname }}</span>
                                <span v-if="r.client?.phone" class="block text-xs text-gray-500">{{ r.client.phone }}</span>
                            </td>
                            <td class="border-b border-[#e3e8ee] px-3 py-2">{{ formatDate(r.scheduled_date) }}</td>
                            <td class="border-b border-[#e3e8ee] px-3 py-2">{{ r.scheduled_time || '—' }}</td>
                            <td class="border-b border-[#e3e8ee] px-3 py-2">{{ channelLabels[r.channel] || r.channel }}</td>
                            <td class="border-b border-[#e3e8ee] px-3 py-2 max-w-[180px] truncate" :title="r.notes">{{ r.notes || '—' }}</td>
                            <td class="border-b border-[#e3e8ee] px-3 py-2">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                        r.status === 'pending' ? 'bg-amber-100 text-amber-800' : '',
                                        r.status === 'completed' ? 'bg-green-100 text-green-800' : '',
                                        r.status === 'cancelled' ? 'bg-gray-100 text-gray-600' : ''
                                    ]"
                                >
                                    {{ statusLabels[r.status] }}
                                </span>
                            </td>
                            <td v-if="statusFilter === 'pending'" class="border-b border-[#e3e8ee] px-3 py-2">
                                <button
                                    type="button"
                                    class="text-red-600 hover:text-red-800 text-xs"
                                    @click="cancelRequest(r)"
                                >
                                    Cancelar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Documentación API -->
        <div class="mt-6 overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <button
                type="button"
                class="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-[#f5f8fa]"
                @click="showApiDocs = !showApiDocs"
            >
                <h3 class="text-lg font-semibold text-[#33475b]">Documentación API</h3>
                <svg
                    :class="['h-5 w-5 text-[#425b76] transition', showApiDocs ? 'rotate-180' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div v-show="showApiDocs" class="border-t border-[#e3e8ee] bg-[#fafbfc] px-6 py-4">
                <p class="mb-4 text-sm text-[#33475b]">
                    Endpoint para programar una llamada o mensaje desde sistemas externos (N8N, integraciones, etc.). La tarea se ejecutará el día y hora indicados en hora Colombia (scheduled_time opcional; si no se envía, se usa 08:00 por defecto).
                </p>

                <div class="mb-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">URL</p>
                    <code class="block break-all rounded bg-[#e3e8ee] px-3 py-2 text-sm">{{ apiCallbackUrl }}</code>
                </div>

                <div class="mb-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Método</p>
                    <code class="rounded bg-[#e3e8ee] px-2 py-1 text-sm">POST</code>
                </div>

                <div class="mb-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Autenticación</p>
                    <p class="mb-2 text-sm text-[#33475b]">
                        Usa la API key de la empresa (generada en la configuración de la empresa). Envía como:
                    </p>
                    <ul class="list-inside list-disc space-y-1 text-sm text-[#33475b]">
                        <li><code class="rounded bg-[#e3e8ee] px-1">Authorization: Bearer ag_xxx...</code></li>
                        <li><code class="rounded bg-[#e3e8ee] px-1">X-Api-Key: ag_xxx...</code></li>
                    </ul>
                </div>

                <div class="mb-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Body (JSON)</p>
                    <pre class="overflow-x-auto rounded bg-[#33475b] p-4 text-sm text-[#e3e8ee]">{
  "client_id": 123,
  "scheduled_date": "2026-02-15",
  "scheduled_time": "09:00",
  "channel": "call",
  "notes": "Cliente solicitó que lo llamen por la mañana"
}</pre>
                    <p class="mt-2 text-xs text-[#425b76]">
                        <strong>Requeridos:</strong> scheduled_date, channel — 
                        <strong>Cliente:</strong> envía client_id, phone o document (al menos uno para identificar al cliente existente) — 
                        <strong>Opcionales:</strong> scheduled_time (HH:mm, hora Colombia), notes
                    </p>
                    <p class="mt-1 text-xs text-[#425b76]">
                        <strong>channel:</strong> "call" (llamada) o "whatsapp" (mensaje)
                    </p>
                </div>

                <div class="mb-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Respuesta exitosa (201)</p>
                    <pre class="overflow-x-auto rounded bg-[#33475b] p-4 text-sm text-[#e3e8ee]">{
  "success": true,
  "message": "Callback programado correctamente. Se ejecutará el día y hora indicados.",
  "callback_request": {
    "id": 1,
    "client_id": 123,
    "client": {
      "id": 123,
      "name": "Juan",
      "lastname": "Pérez",
      "phone": "573001234567"
    },
    "scheduled_date": "2026-02-15",
    "scheduled_time": "09:00",
    "channel": "call",
    "notes": "Cliente solicitó que lo llamen por la mañana",
    "status": "pending"
  }
}</pre>
                </div>

                <div class="mb-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Respuesta error (422)</p>
                    <pre class="overflow-x-auto rounded bg-[#33475b] p-4 text-sm text-[#e3e8ee]">{
  "success": false,
  "error": "Cliente no encontrado. Proporciona client_id, phone o document de un cliente existente."
}</pre>
                </div>

                <div>
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Ejemplo cURL</p>
                    <pre class="overflow-x-auto rounded bg-[#33475b] p-4 text-sm text-[#e3e8ee]">curl -X POST "{{ apiCallbackUrl }}" \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: ag_tu_api_key" \
  -d '{"phone":"573001234567","scheduled_date":"2026-02-15","channel":"call","notes":"Llamar por la mañana"}'</pre>
                </div>

                <div class="mt-4">
                    <p class="mb-1 text-xs font-medium uppercase text-[#425b76]">Ejemplo con client_id</p>
                    <pre class="overflow-x-auto rounded bg-[#33475b] p-4 text-sm text-[#e3e8ee]">curl -X POST "{{ apiCallbackUrl }}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ag_tu_api_key" \
  -d '{"client_id":123,"scheduled_date":"2026-02-15","scheduled_time":"14:00","channel":"whatsapp"}'</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal crear -->
    <div v-show="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="showCreateModal = false">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showCreateModal = false" />
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-medium text-gray-900">Programar callback</h3>
                <p class="mt-1 text-sm text-gray-500">La tarea se ejecutará el día y hora que indiques (por defecto 08:00 si no seleccionas hora).</p>
                <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
                    <div>
                        <InputLabel value="Cliente *" />
                        <select
                            v-model="form.client_id"
                            required
                            class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm"
                        >
                            <option value="">Seleccionar cliente</option>
                            <option v-for="c in clientsList" :key="c.id" :value="c.id">
                                {{ c.name }} {{ c.lastname }} {{ c.phone ? `(${c.phone})` : '' }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Fecha *" />
                        <input
                            v-model="form.scheduled_date"
                            type="date"
                            required
                            :min="new Date().toISOString().split('T')[0]"
                            class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Hora (opcional)" />
                        <input
                            v-model="form.scheduled_time"
                            type="time"
                            class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Canal" />
                        <select v-model="form.channel" class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm">
                            <option value="call">Llamada</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Notas (opcional)" />
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="mt-1 w-full rounded-md border-[#e3e8ee] shadow-sm"
                            placeholder="Ej: Cliente pidió que lo llamen por la tarde"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" class="rounded border px-4 py-2 hover:bg-gray-50" @click="showCreateModal = false">
                            Cancelar
                        </button>
                        <PrimaryButton type="submit" :disabled="creating">
                            {{ creating ? 'Guardando...' : 'Programar' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
