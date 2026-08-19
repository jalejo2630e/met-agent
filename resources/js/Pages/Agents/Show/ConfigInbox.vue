<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    agent: Object,
});

const loading = ref(false);
const conversations = ref([]);
const expanded = ref(null);
const thread = ref([]);
const loadingThread = ref(false);

async function loadInbox() {
    loading.value = true;
    try {
        const { data } = await axios.get(route('agents.twilio.inbox.index', props.agent));
        conversations.value = data.conversations ?? [];
    } catch (e) {
        // silencioso
    } finally {
        loading.value = false;
    }
}

async function toggleThread(from) {
    if (expanded.value === from) {
        expanded.value = null;
        thread.value = [];
        return;
    }
    expanded.value = from;
    loadingThread.value = true;
    try {
        const { data } = await axios.get(route('agents.twilio.inbox.thread', props.agent), { params: { from } });
        thread.value = data.messages ?? [];
    } catch (e) {
        thread.value = [];
    } finally {
        loadingThread.value = false;
    }
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
    <div class="space-y-4">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="flex items-center justify-between border-b border-[#e3e8ee] px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-[#33475b]">Bandeja de mensajes (Twilio)</h3>
                    <p class="mt-1 text-sm text-[#425b76]">Mensajes de WhatsApp/SMS recibidos por el webhook. Si el número no tiene cliente, créalo desde aquí.</p>
                </div>
                <button type="button" class="text-sm font-medium text-emerald-600 hover:text-emerald-700" @click="loadInbox">Actualizar</button>
            </div>
            <div class="p-6">
                <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>
                <p v-else-if="!conversations.length" class="text-sm text-gray-400">Aún no hay mensajes recibidos por el webhook.</p>
                <ul v-else class="divide-y divide-[#eef2f6] rounded-md border border-[#eef2f6]">
                    <li v-for="c in conversations" :key="c.from_number" class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-[#33475b]">{{ c.profile_name || c.from_number }}</span>
                                    <span class="font-mono text-xs text-gray-400">{{ c.from_number }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-sm text-[#425b76]">
                                    <span v-if="c.last_message.direction === 'outbound'" class="text-xs text-gray-400">Tú: </span>
                                    {{ c.last_message.body }}
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ c.messages_count }} msj</span>
                            <span
                                v-if="c.client"
                                class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700"
                                :title="'Cliente #' + c.client.id"
                            >Cliente: {{ c.client.name }}</span>
                            <template v-else>
                                <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Sin cliente</span>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                    @click="openCreate(c)"
                                >Crear cliente</button>
                            </template>
                            <button type="button" class="shrink-0 text-xs text-[#425b76] underline hover:text-[#33475b]" @click="toggleThread(c.from_number)">
                                {{ expanded === c.from_number ? 'Ocultar' : 'Ver conversación' }}
                            </button>
                        </div>

                        <div v-if="expanded === c.from_number" class="mt-3 rounded-md bg-[#f5f8fa] p-3">
                            <p v-if="loadingThread" class="text-xs text-gray-400">Cargando…</p>
                            <div v-else class="space-y-1.5">
                                <div v-for="m in thread" :key="m.id" class="text-sm" :class="m.direction === 'outbound' ? 'text-right' : 'text-left'">
                                    <span
                                        class="inline-block max-w-[80%] rounded-lg px-3 py-1.5"
                                        :class="m.direction === 'outbound' ? 'bg-emerald-100 text-[#0f5132]' : 'border border-[#e3e8ee] bg-white text-[#33475b]'"
                                    >{{ m.body }}</span>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
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
