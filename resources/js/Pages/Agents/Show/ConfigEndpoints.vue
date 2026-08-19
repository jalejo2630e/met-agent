<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref } from 'vue';
import axios from 'axios';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const baseClientParams = [
    { value: 'email', label: 'Email' },
    { value: 'document', label: 'Documento' },
    { value: 'document_type', label: 'Tipo documento' },
    { value: 'name', label: 'Nombre' },
    { value: 'lastname', label: 'Apellido' },
];

const clientParams = computed(() => {
    const custom = (props.agent.client_fields || []).map(f => ({ value: f.field_name, label: f.field_name }));
    return [...baseClientParams, ...custom];
});

const showEditModal = ref(false);
const showTestModal = ref(false);
const editingEndpoint = ref(null);
const testResult = ref(null);
const testLoading = ref(false);

const form = useForm({
    name: '',
    url: '',
    method: 'GET',
    client_parameter: 'email',
    headers: [],
});

const editForm = useForm({
    name: '',
    url: '',
    method: 'GET',
    client_parameter: 'email',
    headers: [],
});

const testForm = ref({ body: '{}' });

function headersToArray(headersObj) {
    if (!headersObj || typeof headersObj !== 'object') return [];
    return Object.entries(headersObj).map(([k, v]) => ({ key: k, value: v || '' })).filter(h => h.key);
}

function headersToObject(arr) {
    const obj = {};
    (arr || []).filter(h => h?.key?.trim()).forEach(h => { obj[h.key.trim()] = h.value || ''; });
    return obj;
}

function addHeader(targetForm = 'create') {
    if (targetForm === 'create') {
        form.headers.push({ key: '', value: '' });
    } else {
        editForm.headers.push({ key: '', value: '' });
    }
}

function removeHeader(idx, targetForm = 'create') {
    if (targetForm === 'create') {
        form.headers.splice(idx, 1);
    } else {
        editForm.headers.splice(idx, 1);
    }
}

const submit = () => {
    const data = { ...form.data(), headers: headersToObject(form.headers) };
    form.transform(() => data).post(route('agents.endpoints.store', props.agent), {
        onSuccess: () => {
            form.reset();
            form.headers = [];
        },
    });
};

const openEdit = (ep) => {
    editingEndpoint.value = ep;
    editForm.name = ep.name;
    editForm.url = ep.url;
    editForm.method = ep.method;
    editForm.client_parameter = ep.client_parameter;
    editForm.headers = headersToArray(ep.headers);
    if (!editForm.headers.length) editForm.headers = [{ key: '', value: '' }];
    showEditModal.value = true;
};

const submitEdit = () => {
    const data = { ...editForm.data(), headers: headersToObject(editForm.headers) };
    editForm.transform(() => data).patch(route('agents.endpoints.update', [props.agent, editingEndpoint.value]), {
        onSuccess: () => {
            showEditModal.value = false;
            editingEndpoint.value = null;
        },
    });
};

const openTest = (ep) => {
    editingEndpoint.value = ep;
    testForm.value = { body: '{}' };
    testResult.value = null;
    showTestModal.value = true;
};

const runTest = async () => {
    testLoading.value = true;
    testResult.value = null;
    try {
        const { data } = await axios.post(
            route('agents.endpoints.test', [props.agent, editingEndpoint.value]),
            { body: testForm.value.body },
            { headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' } }
        );
        testResult.value = data;
    } catch (err) {
        testResult.value = {
            success: false,
            error: err.response?.data?.error || err.message,
            status: err.response?.status,
        };
    } finally {
        testLoading.value = false;
    }
};

const deleteEndpoint = (endpoint) => {
    if (confirm('¿Eliminar este endpoint?')) {
        router.delete(route('agents.endpoints.destroy', [props.agent, endpoint]));
    }
};
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900">Endpoints precargados</h3>
            <p class="mt-1 text-sm text-gray-500">
                Endpoints que se ejecutan antes del contacto. Envía el parámetro del cliente seleccionado y usa la respuesta como contexto para la empresa.
            </p>

            <form @submit.prevent="submit" class="mt-6 space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Nombre" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="Parámetro del cliente" />
                        <select
                            v-model="form.client_parameter"
                            class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        >
                            <option v-for="p in clientParams" :key="p.value" :value="p.value">
                                {{ p.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <InputLabel value="URL" />
                        <TextInput v-model="form.url" type="url" class="mt-1 block w-full" required placeholder="https://..." />
                        <InputError :message="form.errors.url" />
                    </div>
                    <div>
                        <InputLabel value="Método" />
                        <select
                            v-model="form.method"
                            class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                        >
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <InputLabel value="Headers (autenticación, etc.)" />
                        <button type="button" class="text-sm text-[var(--color-primary)]" @click="addHeader('create')">+ Agregar header</button>
                    </div>
                    <div v-for="(h, idx) in form.headers" :key="idx" class="mt-2 flex gap-2">
                        <TextInput v-model="h.key" class="flex-1" placeholder="Authorization" />
                        <TextInput v-model="h.value" class="flex-1" placeholder="Bearer xxx" />
                        <button type="button" class="text-red-600" @click="removeHeader(idx, 'create')">×</button>
                    </div>
                </div>

                <PrimaryButton :disabled="form.processing">Agregar endpoint</PrimaryButton>
            </form>

            <div v-if="agent.endpoints?.length" class="mt-6">
                <h4 class="text-sm font-medium text-gray-700">Endpoints configurados</h4>
                <ul class="mt-2 divide-y divide-gray-200">
                    <li
                        v-for="ep in agent.endpoints"
                        :key="ep.id"
                        class="flex items-center justify-between py-3"
                    >
                        <div>
                            <span class="font-medium">{{ ep.name }}</span>
                            <span class="ml-2 text-sm text-gray-500">{{ ep.url }}</span>
                            <span class="ml-2 rounded bg-gray-100 px-1 text-xs">{{ ep.client_parameter }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="text-[var(--color-primary)] hover:text-[var(--color-primary-active)]" @click="openEdit(ep)">
                                Editar
                            </button>
                            <button type="button" class="text-green-600 hover:text-green-800" @click="openTest(ep)">
                                Probar
                            </button>
                            <button type="button" class="text-red-600 hover:text-red-800" @click="deleteEndpoint(ep)">
                                Eliminar
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div v-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showEditModal = false" />
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-medium">Editar endpoint</h3>
                <form @submit.prevent="submitEdit" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Nombre" />
                            <TextInput v-model="editForm.name" class="mt-1 w-full" required />
                        </div>
                        <div>
                            <InputLabel value="Parámetro del cliente" />
                            <select v-model="editForm.client_parameter" class="mt-1 block w-full rounded-md border-[#e3e8ee]">
                                <option v-for="p in clientParams" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="URL" />
                        <TextInput v-model="editForm.url" type="url" class="mt-1 w-full" required />
                    </div>
                    <div>
                        <InputLabel value="Método" />
                        <select v-model="editForm.method" class="mt-1 block w-full rounded-md border-[#e3e8ee]">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Headers" />
                            <button type="button" class="text-sm text-[var(--color-primary)]" @click="addHeader('edit')">+ Agregar</button>
                        </div>
                        <div v-for="(h, idx) in editForm.headers" :key="idx" class="mt-2 flex gap-2">
                            <TextInput v-model="h.key" class="flex-1" placeholder="Header" />
                            <TextInput v-model="h.value" class="flex-1" placeholder="Valor" />
                            <button type="button" class="text-red-600" @click="removeHeader(idx, 'edit')">×</button>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <SecondaryButton type="button" @click="showEditModal = false">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="editForm.processing">Guardar</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Probar -->
    <div v-show="showTestModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showTestModal = false" />
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-medium">Probar endpoint — {{ editingEndpoint?.name }}</h3>
                <p class="mt-1 text-sm text-gray-500">Body de ejemplo (JSON). Solo aplica para POST.</p>
                <div class="mt-4">
                    <textarea
                        v-model="testForm.body"
                        rows="8"
                        class="block w-full rounded-md border-[#e3e8ee] font-mono text-sm"
                        placeholder='{"key": "value"}'
                    />
                </div>
                <div class="mt-4 flex gap-2">
                    <PrimaryButton :disabled="testLoading" @click="runTest">
                        {{ testLoading ? 'Probando...' : 'Probar' }}
                    </PrimaryButton>
                    <SecondaryButton type="button" @click="showTestModal = false">Cerrar</SecondaryButton>
                </div>
                <div v-if="testResult" class="mt-4 rounded-lg p-4" :class="testResult.success ? 'bg-green-50' : 'bg-red-50'">
                    <h4 class="font-medium" :class="testResult.success ? 'text-green-800' : 'text-red-800'">
                        {{ testResult.success ? '✓ Respuesta exitosa' : '✗ Error' }}
                    </h4>
                    <div v-if="testResult.status" class="mt-1 text-sm">Status: {{ testResult.status }}</div>
                    <div v-if="testResult.error" class="mt-1 text-sm text-red-700">{{ testResult.error }}</div>
                    <pre v-if="testResult.body" class="mt-2 max-h-64 overflow-auto rounded bg-white/50 p-2 text-xs">{{ typeof testResult.body === 'object' ? JSON.stringify(testResult.body, null, 2) : testResult.body }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>
