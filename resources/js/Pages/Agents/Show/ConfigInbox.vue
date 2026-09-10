<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    agent: Object,
});

const loading = ref(false);
const conversations = ref([]);
const selected = ref(null); // from_number de la conversación abierta
const thread = ref([]);
const loadingThread = ref(false);
const search = ref('');
const extraction = ref({ variables: [], values: {}, updated_at: null });
const extracting = ref(false);

// Toma de control humano
const botPaused = ref(false);
const togglingPause = ref(false);
const replyText = ref('');
const sendingReply = ref(false);
const selectedPlantillaId = ref('');
const sendingTemplate = ref(false);
const showTemplates = ref(false);
const notes = ref([]);
const newNote = ref('');
const savingNote = ref(false);

// Plantillas de Twilio (las únicas enviables desde la bandeja).
const twilioPlantillas = computed(() =>
    (props.agent?.message_config?.plantillas || []).filter((p) => {
        const id = (p?.id ?? '').toString().trim();
        return id && (p.from_twilio || id.startsWith('HX'));
    })
);

const filteredConversations = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return conversations.value;
    return conversations.value.filter((c) => {
        return (
            (c.profile_name || '').toLowerCase().includes(q) ||
            (c.from_number || '').toLowerCase().includes(q) ||
            (c.client?.name || '').toLowerCase().includes(q) ||
            (c.last_message?.body || '').toLowerCase().includes(q)
        );
    });
});

const selectedConv = computed(
    () => conversations.value.find((c) => c.from_number === selected.value) || null
);

async function loadInbox() {
    loading.value = true;
    try {
        const { data } = await axios.get(route('agents.twilio.inbox.index', props.agent));
        conversations.value = data.conversations ?? [];
        // Si la conversación abierta ya no existe, ciérrala.
        if (selected.value && !conversations.value.some((c) => c.from_number === selected.value)) {
            selected.value = null;
            thread.value = [];
        }
    } catch (e) {
        // silencioso
    } finally {
        loading.value = false;
    }
}

async function selectConversation(conv) {
    selected.value = conv.from_number;
    thread.value = [];
    extraction.value = { variables: [], values: {}, updated_at: null };
    notes.value = [];
    replyText.value = '';
    newNote.value = '';
    selectedPlantillaId.value = '';
    showTemplates.value = false;
    botPaused.value = !!conv.bot_paused;
    loadingThread.value = true;
    try {
        const { data } = await axios.get(route('agents.twilio.inbox.thread', props.agent), {
            params: { from: conv.from_number },
        });
        thread.value = data.messages ?? [];
        extraction.value = data.extraction ?? { variables: [], values: {}, updated_at: null };
        notes.value = data.notes ?? [];
        botPaused.value = !!data.bot_paused;
    } catch (e) {
        thread.value = [];
    } finally {
        loadingThread.value = false;
    }
}

async function togglePause() {
    if (!selected.value) return;
    const next = !botPaused.value;
    togglingPause.value = true;
    try {
        const { data } = await axios.post(route('agents.twilio.inbox.pause', props.agent), {
            from: selected.value,
            paused: next,
        });
        botPaused.value = !!data.bot_paused;
        if (selectedConv.value) selectedConv.value.bot_paused = botPaused.value;
        toast.success(botPaused.value
            ? 'Bot pausado: ahora respondes tú. La IA no contestará.'
            : 'Bot reactivado: la IA vuelve a responder automáticamente.');
    } catch (e) {
        toast.error('No se pudo cambiar el estado del bot.');
    } finally {
        togglingPause.value = false;
    }
}

async function sendReply() {
    const body = replyText.value.trim();
    if (!selected.value || !body) return;
    sendingReply.value = true;
    try {
        const { data } = await axios.post(route('agents.twilio.inbox.reply', props.agent), {
            from: selected.value,
            body,
        });
        if (data.success) {
            if (data.new_message) thread.value = [...thread.value, data.new_message];
            replyText.value = '';
            toast.success('Mensaje enviado.');
        } else {
            toast.error(data.message || 'No se pudo enviar.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo enviar el mensaje.');
    } finally {
        sendingReply.value = false;
    }
}

async function sendTemplateMsg() {
    if (!selected.value) return;
    if (!confirm('¿Enviar esta plantilla de WhatsApp?')) return;
    sendingTemplate.value = true;
    try {
        const { data } = await axios.post(route('agents.twilio.inbox.template', props.agent), {
            from: selected.value,
            id_plantilla: selectedPlantillaId.value || undefined,
        });
        if (data.success) {
            if (data.new_message) thread.value = [...thread.value, data.new_message];
            showTemplates.value = false;
            toast.success('Plantilla enviada.');
        } else {
            toast.error(data.message || 'No se pudo enviar la plantilla.');
        }
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo enviar la plantilla.');
    } finally {
        sendingTemplate.value = false;
    }
}

async function addNote() {
    const body = newNote.value.trim();
    if (!selected.value || !body) return;
    savingNote.value = true;
    try {
        const { data } = await axios.post(route('agents.twilio.inbox.notes.store', props.agent), {
            from: selected.value,
            body,
        });
        notes.value = data.notes ?? notes.value;
        newNote.value = '';
    } catch (e) {
        toast.error('No se pudo guardar la nota.');
    } finally {
        savingNote.value = false;
    }
}

async function removeNote(note) {
    if (!confirm('¿Eliminar esta nota?')) return;
    try {
        const { data } = await axios.delete(route('agents.twilio.inbox.notes.destroy', [props.agent, note.id]));
        notes.value = data.notes ?? notes.value.filter((n) => n.id !== note.id);
    } catch (e) {
        toast.error('No se pudo eliminar la nota.');
    }
}

async function reExtract() {
    if (!selected.value) return;
    extracting.value = true;
    try {
        const { data } = await axios.post(route('agents.twilio.inbox.extract', props.agent), { from: selected.value });
        extraction.value = data.extraction ?? extraction.value;
    } catch (e) {
        // silencioso
    } finally {
        extracting.value = false;
    }
}

function displayValue(name) {
    const v = extraction.value.values?.[name];
    if (v === undefined || v === null || v === '') return null;
    if (typeof v === 'boolean') return v ? 'Sí' : 'No';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
}

function backToList() {
    selected.value = null;
    thread.value = [];
}

function initials(conv) {
    const name = (conv.profile_name || conv.client?.name || conv.from_number || '?').trim();
    const parts = name.split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
}

function fmtTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) {
        return d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
    }
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
    return d.toLocaleDateString('es', { day: '2-digit', month: '2-digit' });
}

function fmtFull(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString('es', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// Crear cliente desde un mensaje de un número sin cliente.
const showCreate = ref(false);
const createForm = useForm({ name: '', lastname: '', email: '', phone: '' });

function openCreate(conv) {
    createForm.reset();
    createForm.clearErrors();
    createForm.phone = (conv.from_number || '').replace(/\D/g, '');
    createForm.name = conv.profile_name || '';
    showCreate.value = true;
}

function submitCreate() {
    createForm.post(route('agents.clients.store', props.agent), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            loadInbox();
        },
    });
}

onMounted(loadInbox);
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
        <!-- Encabezado -->
        <div class="flex items-center justify-between border-b border-[#e3e8ee] px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-[#33475b]">Bandeja de mensajes (Twilio)</h3>
                <p class="mt-1 text-sm text-[#425b76]">Mensajes de WhatsApp/SMS recibidos por el webhook. Si el número no tiene cliente, créalo desde aquí.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700"
                @click="loadInbox"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                Actualizar
            </button>
        </div>

        <!-- Layout tipo correo -->
        <div class="flex h-[65vh] min-h-[420px]">
            <!-- Lista de conversaciones (izquierda) -->
            <div
                class="flex w-full flex-col border-r border-[#e3e8ee] md:w-[340px] md:shrink-0"
                :class="selected ? 'hidden md:flex' : 'flex'"
            >
                <!-- Buscador -->
                <div class="border-b border-[#eef2f6] p-3">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar conversación…"
                            class="w-full rounded-md border-[#e3e8ee] bg-[#f5f8fa] py-2 pl-9 pr-3 text-sm text-[#33475b] focus:border-emerald-400 focus:bg-white focus:ring-emerald-400"
                        />
                    </div>
                </div>

                <!-- Listado -->
                <div class="flex-1 overflow-y-auto">
                    <p v-if="loading" class="p-6 text-sm text-gray-400">Cargando…</p>
                    <p v-else-if="!conversations.length" class="p-6 text-sm text-gray-400">Aún no hay mensajes recibidos por el webhook.</p>
                    <p v-else-if="!filteredConversations.length" class="p-6 text-sm text-gray-400">Sin resultados para “{{ search }}”.</p>
                    <ul v-else class="divide-y divide-[#eef2f6]">
                        <li
                            v-for="c in filteredConversations"
                            :key="c.from_number"
                            role="button"
                            tabindex="0"
                            class="flex cursor-pointer items-start gap-3 px-4 py-3 transition"
                            :class="selected === c.from_number ? 'bg-emerald-50' : 'hover:bg-[#f5f8fa]'"
                            @click="selectConversation(c)"
                            @keydown.enter="selectConversation(c)"
                        >
                            <!-- Avatar -->
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                :class="c.client ? 'bg-emerald-100 text-emerald-700' : 'bg-[#e3e8ee] text-[#425b76]'"
                            >{{ initials(c) }}</div>

                            <!-- Resumen -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium text-[#33475b]">{{ c.profile_name || c.client?.name || c.from_number }}</span>
                                    <span class="ml-auto shrink-0 text-xs text-gray-400">{{ fmtTime(c.last_message?.created_at) }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-sm text-[#425b76]">
                                    <span v-if="c.last_message?.direction === 'outbound'" class="text-gray-400">Tú: </span>
                                    {{ c.last_message?.body }}
                                </p>
                                <div class="mt-1 flex items-center gap-1.5">
                                    <span class="rounded-full bg-[#f5f8fa] px-2 py-0.5 text-[11px] text-gray-500">{{ c.messages_count }} msj</span>
                                    <span
                                        v-if="c.client"
                                        class="truncate rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] text-emerald-700"
                                    >Cliente</span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-700"
                                    >Sin cliente</span>
                                    <span
                                        v-if="c.bot_paused"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800"
                                        title="Bot pausado — atención humana"
                                    ><span class="h-1.5 w-1.5 rounded-full bg-amber-500" />Humano</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Conversación seleccionada (derecha) -->
            <div
                class="min-w-0 flex-1 flex-col bg-[#f5f8fa]"
                :class="selected ? 'flex' : 'hidden md:flex'"
            >
                <!-- Estado vacío -->
                <div v-if="!selectedConv" class="flex flex-1 flex-col items-center justify-center px-6 text-center">
                    <svg class="h-12 w-12 text-[#cbd6e2]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                    <p class="mt-3 text-sm text-[#425b76]">Selecciona una conversación para verla aquí.</p>
                </div>

                <template v-else>
                    <!-- Cabecera de la conversación -->
                    <div class="flex items-center gap-3 border-b border-[#e3e8ee] bg-white px-4 py-3">
                        <button
                            type="button"
                            class="-ml-1 rounded-md p-1 text-[#425b76] hover:bg-[#f5f8fa] md:hidden"
                            title="Volver"
                            @click="backToList"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="selectedConv.client ? 'bg-emerald-100 text-emerald-700' : 'bg-[#e3e8ee] text-[#425b76]'"
                        >{{ initials(selectedConv) }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-semibold text-[#33475b]">{{ selectedConv.profile_name || selectedConv.client?.name || selectedConv.from_number }}</span>
                                <span
                                    v-if="selectedConv.client"
                                    class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700"
                                    :title="'Cliente #' + selectedConv.client.id"
                                >Cliente: {{ selectedConv.client.name }}</span>
                            </div>
                            <span class="font-mono text-xs text-gray-400">{{ selectedConv.from_number }}</span>
                        </div>
                        <!-- Pausar / reactivar el bot (toma de control) -->
                        <button
                            type="button"
                            :disabled="togglingPause"
                            class="inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition disabled:opacity-60"
                            :class="botPaused ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'"
                            :title="botPaused ? 'El bot está pausado; respondes tú' : 'El bot responde automáticamente'"
                            @click="togglePause"
                        >
                            <span :class="['h-2 w-2 rounded-full', botPaused ? 'bg-amber-500' : 'bg-emerald-500']" />
                            {{ togglingPause ? '…' : (botPaused ? 'Bot pausado — reactivar' : 'Tomar control') }}
                        </button>
                        <button
                            v-if="!selectedConv.client"
                            type="button"
                            class="shrink-0 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                            @click="openCreate(selectedConv)"
                        >Crear cliente</button>
                    </div>

                    <!-- Variables extraídas de la conversación -->
                    <div v-if="extraction.variables.length" class="border-b border-[#e3e8ee] bg-white px-4 py-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-[#425b76]">Variables extraídas</h4>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700 disabled:opacity-60"
                                :disabled="extracting"
                                @click="reExtract"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                {{ extracting ? 'Extrayendo…' : 'Re-extraer' }}
                            </button>
                        </div>
                        <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2">
                            <div v-for="v in extraction.variables" :key="v.name" class="flex items-baseline gap-2 text-sm">
                                <span class="shrink-0 text-[#425b76]">{{ v.label || v.name }}:</span>
                                <span v-if="displayValue(v.name)" class="min-w-0 break-words font-medium text-[#33475b]">{{ displayValue(v.name) }}</span>
                                <span v-else class="text-gray-300">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notas internas (no visibles para el cliente) -->
                    <div class="border-b border-[#e3e8ee] bg-amber-50/60 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-amber-700">Notas internas</h4>
                            <span class="text-[11px] text-amber-600">Solo tú las ves · no se envían al cliente</span>
                        </div>
                        <div v-if="notes.length" class="mt-2 space-y-1.5">
                            <div v-for="n in notes" :key="n.id" class="group flex items-start gap-2 rounded-md border border-amber-200 bg-white px-2.5 py-1.5 text-sm">
                                <div class="min-w-0 flex-1">
                                    <p class="whitespace-pre-wrap break-words text-[#33475b]">{{ n.body }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-400">{{ n.user || 'Operador' }} · {{ fmtFull(n.created_at) }}</p>
                                </div>
                                <button type="button" class="shrink-0 text-gray-300 opacity-0 transition hover:text-red-500 group-hover:opacity-100" title="Eliminar" @click="removeNote(n)">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                v-model="newNote"
                                type="text"
                                placeholder="Agregar una nota interna…"
                                class="min-w-0 flex-1 rounded-md border-amber-200 bg-white py-1.5 text-sm text-[#33475b] focus:border-amber-400 focus:ring-amber-400"
                                @keydown.enter.prevent="addNote"
                            />
                            <button
                                type="button"
                                class="shrink-0 rounded-md border border-amber-300 bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-200 disabled:opacity-60"
                                :disabled="savingNote || !newNote.trim()"
                                @click="addNote"
                            >{{ savingNote ? '…' : 'Agregar' }}</button>
                        </div>
                    </div>

                    <!-- Hilo de mensajes -->
                    <div class="flex-1 overflow-y-auto px-4 py-4">
                        <p v-if="loadingThread" class="text-sm text-gray-400">Cargando…</p>
                        <div v-else-if="!thread.length" class="text-sm text-gray-400">No hay mensajes en esta conversación.</div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="m in thread"
                                :key="m.id"
                                class="flex"
                                :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                            >
                                <div
                                    class="max-w-[75%] rounded-2xl px-3 py-2 text-sm shadow-sm"
                                    :class="m.direction === 'outbound'
                                        ? 'rounded-br-sm bg-emerald-100 text-[#0f5132]'
                                        : 'rounded-bl-sm border border-[#e3e8ee] bg-white text-[#33475b]'"
                                >
                                    <p class="whitespace-pre-wrap break-words">{{ m.body }}</p>
                                    <p
                                        class="mt-1 text-right text-[11px]"
                                        :class="m.direction === 'outbound' ? 'text-emerald-600/70' : 'text-gray-400'"
                                    >{{ fmtFull(m.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Composer: responder como humano + plantillas -->
                    <div class="border-t border-[#e3e8ee] bg-white px-4 py-3">
                        <!-- Selector de plantilla (desplegable) -->
                        <div v-if="showTemplates" class="mb-2 rounded-md border border-[#e3e8ee] bg-[#f9fbfd] p-3">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-[#425b76]">Plantilla de WhatsApp (Meta/Twilio)</label>
                                <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="showTemplates = false">Cerrar</button>
                            </div>
                            <div v-if="twilioPlantillas.length" class="mt-2 flex flex-wrap items-center gap-2">
                                <select v-model="selectedPlantillaId" class="min-w-0 flex-1 rounded-md border-[#e3e8ee] text-sm">
                                    <option value="">— Plantilla por defecto —</option>
                                    <option v-for="p in twilioPlantillas" :key="p.id" :value="p.id">{{ p.name || p.id }}</option>
                                </select>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#20BD5A] disabled:opacity-60"
                                    :disabled="sendingTemplate"
                                    @click="sendTemplateMsg"
                                >{{ sendingTemplate ? 'Enviando…' : 'Enviar plantilla' }}</button>
                            </div>
                            <p v-else class="mt-2 text-xs text-gray-500">
                                No hay plantillas de Twilio configuradas. Agrégalas en <strong>Comunicación → Mensajes → Plantillas</strong> (opción “Agregar desde Twilio”).
                            </p>
                        </div>

                        <div class="flex items-end gap-2">
                            <textarea
                                v-model="replyText"
                                rows="1"
                                placeholder="Escribe una respuesta…"
                                class="max-h-32 min-h-[42px] flex-1 resize-y rounded-lg border-[#e3e8ee] text-sm text-[#33475b] focus:border-emerald-400 focus:ring-emerald-400"
                                @keydown.enter.exact.prevent="sendReply"
                            />
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#e3e8ee] px-3 py-2.5 text-sm text-[#425b76] transition hover:bg-[#f5f8fa]"
                                title="Enviar plantilla de Meta"
                                @click="showTemplates = !showTemplates"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                Plantillas
                            </button>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#25D366] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#20BD5A] disabled:opacity-60"
                                :disabled="sendingReply || !replyText.trim()"
                                @click="sendReply"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                {{ sendingReply ? 'Enviando…' : 'Enviar' }}
                            </button>
                        </div>
                        <p v-if="!botPaused" class="mt-1.5 text-[11px] text-amber-600">
                            El bot sigue activo y también responderá. Usa “Tomar control” para pausarlo mientras atiendes tú.
                        </p>
                        <p class="mt-1 text-[11px] text-gray-400">
                            El texto libre solo funciona dentro de las 24h del último mensaje del cliente; fuera de esa ventana, usa una plantilla.
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal: crear cliente desde el mensaje -->
        <Teleport to="body">
            <div v-show="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/50" @click="showCreate = false" />
                <div class="relative w-full max-w-md overflow-hidden rounded-lg border border-[#e3e8ee] bg-white shadow-xl">
                    <div class="border-b border-[#e3e8ee] bg-[#f5f8fa] px-4 py-3">
                        <h2 class="text-lg font-semibold text-[#33475b]">Crear cliente desde el mensaje</h2>
                    </div>
                    <form @submit.prevent="submitCreate" class="space-y-3 p-4">
                        <div>
                            <InputLabel value="Nombre *" />
                            <TextInput v-model="createForm.name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Apellido *" />
                            <TextInput v-model="createForm.lastname" type="text" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.lastname" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Email *" />
                            <TextInput v-model="createForm.email" type="email" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.email" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Teléfono" />
                            <TextInput v-model="createForm.phone" type="text" class="mt-1 block w-full font-mono" />
                            <InputError :message="createForm.errors.phone" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-2 border-t border-[#e3e8ee] pt-3">
                            <button type="button" class="rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#33475b] hover:bg-[#f5f8fa]" @click="showCreate = false">Cancelar</button>
                            <PrimaryButton type="submit" :disabled="createForm.processing">{{ createForm.processing ? 'Creando...' : 'Crear cliente' }}</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
