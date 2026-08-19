<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, computed } from 'vue';
import axios from 'axios';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const apiBaseUrl = computed(() => (typeof window !== 'undefined' ? window.location.origin : ''));
const apiClientsUrl = computed(() => `${apiBaseUrl.value}/api/agents/${props.agent?.id ?? ''}/clients`);

const showImportModal = ref(false);
const showEditModal = ref(false);
const editingEndpoint = ref(null);
const uploadProgress = ref(0);
const importLoading = ref(false);
const importResult = ref(null);

const form = useForm({
    name: '',
    url: '',
    headers: [],
    schedule_time: '08:00',
    schedule_timezone: 'America/Bogota',
});

const editForm = useForm({
    name: '',
    url: '',
    headers: [],
    schedule_time: '08:00',
    schedule_timezone: 'America/Bogota',
});

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

const timeOptions = (() => {
    const opts = [];
    for (let h = 0; h < 24; h++) {
        for (let m = 0; m < 60; m += 30) {
            const t = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
            opts.push({ value: t, label: t });
        }
    }
    return opts;
})();

const timezones = [
    { value: 'America/Bogota', label: 'Bogotá' },
    { value: 'America/Mexico_City', label: 'Ciudad de México' },
    { value: 'America/Argentina/Buenos_Aires', label: 'Buenos Aires' },
    { value: 'America/Lima', label: 'Lima' },
    { value: 'America/Santiago', label: 'Santiago' },
    { value: 'Europe/Madrid', label: 'Madrid' },
    { value: 'UTC', label: 'UTC' },
];

const submit = () => {
    const data = { ...form.data(), headers: headersToObject(form.headers) };
    form.transform(() => data).post(route('agents.client-source-endpoints.store', props.agent), {
        onSuccess: () => {
            form.reset();
            form.headers = [];
            form.schedule_time = '08:00';
        },
    });
};

const openEdit = (ep) => {
    editingEndpoint.value = ep;
    editForm.name = ep.name;
    editForm.url = ep.url;
    editForm.headers = headersToArray(ep.headers);
    if (!editForm.headers.length) editForm.headers = [{ key: '', value: '' }];
    editForm.schedule_time = ep.schedule_time || '08:00';
    editForm.schedule_timezone = ep.schedule_timezone || 'America/Bogota';
    showEditModal.value = true;
};

const submitEdit = () => {
    const data = { ...editForm.data(), headers: headersToObject(editForm.headers) };
    editForm.transform(() => data).put(route('agents.client-source-endpoints.update', [props.agent, editingEndpoint.value]), {
        onSuccess: () => {
            showEditModal.value = false;
            editingEndpoint.value = null;
        },
    });
};

const deleteEndpoint = (ep) => {
    if (confirm('¿Eliminar este endpoint?')) {
        router.delete(route('agents.client-source-endpoints.destroy', [props.agent, ep]));
    }
};

const openImport = () => {
    showImportModal.value = true;
    uploadProgress.value = 0;
    importResult.value = null;
    importLoading.value = false;
};

const downloadTemplate = (format = 'xlsx') => {
    const url = `${route('agents.clients.template', props.agent)}?format=${format}`;
    window.location.href = url;
};

const fileInput = ref(null);
const selectedFile = ref(null);

const onFileSelect = (e) => {
    const f = e.target.files?.[0];
    selectedFile.value = f || null;
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const submitImport = async () => {
    if (!selectedFile.value) return;
    importLoading.value = true;
    uploadProgress.value = 0;
    importResult.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    try {
        const { data } = await axios.post(
            route('agents.clients.import', props.agent),
            formData,
            {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                onUploadProgress: (e) => {
                    uploadProgress.value = e.loaded && e.total ? Math.round((e.loaded / e.total) * 100) : 0;
                },
            }
        );
        importResult.value = {
            success: true,
            message: data?.message || 'Importación completada.',
            errors: data?.errors || [],
        };
        selectedFile.value = null;
        fileInput.value.value = '';
        router.reload();
    } catch (err) {
        const data = err.response?.data;
        const msg = data?.message || data?.errors?.file?.[0] || err.message;
        const errors = data?.errors?.import_errors ?? data?.errors ?? (Array.isArray(data?.errors) ? data.errors : []);
        importResult.value = { success: false, message: msg, errors: Array.isArray(errors) ? errors : [errors] };
    } finally {
        importLoading.value = false;
        uploadProgress.value = 100;
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Endpoints para traer clientes -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Endpoints de fuente de clientes</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Configura endpoints externos para obtener clientes automáticamente. Define la hora de ejecución.
                </p>

                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Nombre" />
                            <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="ej: API CRM" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="URL" />
                            <TextInput v-model="form.url" type="url" class="mt-1 block w-full" placeholder="https://..." required />
                            <InputError :message="form.errors.url" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Hora de ejecución" />
                            <select
                                v-model="form.schedule_time"
                                class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            >
                                <option v-for="t in timeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Zona horaria" />
                            <select
                                v-model="form.schedule_timezone"
                                class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            >
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
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

                <div v-if="agent.client_source_endpoints?.length" class="mt-6">
                    <h4 class="text-sm font-medium text-gray-700">Endpoints configurados</h4>
                    <ul class="mt-2 divide-y divide-gray-200">
                        <li
                            v-for="ep in agent.client_source_endpoints"
                            :key="ep.id"
                            class="flex items-center justify-between py-3"
                        >
                            <div>
                                <span class="font-medium">{{ ep.name }}</span>
                                <span class="ml-2 text-sm text-gray-500">{{ ep.url }}</span>
                                <span v-if="ep.schedule_time" class="ml-2 rounded bg-indigo-100 px-2 py-0.5 text-xs text-indigo-800">
                                    {{ ep.schedule_time }} ({{ ep.schedule_timezone || 'UTC' }})
                                </span>
                                <span v-if="ep.headers && Object.keys(ep.headers).length" class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                    Headers configurados
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="text-[var(--color-primary)] hover:text-[var(--color-primary-active)]" @click="openEdit(ep)">Editar</button>
                                <button type="button" class="text-red-600 hover:text-red-800" @click="deleteEndpoint(ep)">Eliminar</button>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- API para crear clientes -->
                <div class="mt-8 rounded-lg border border-[#e3e8ee] bg-[#f5f8fa] p-4">
                    <h4 class="text-sm font-semibold text-[#33475b]">API para crear o actualizar clientes</h4>
                    <p class="mt-1 text-sm text-[#425b76]">
                        Desde N8N, integraciones o cualquier sistema externo puedes crear o actualizar clientes de la empresa con la API. Si el cliente ya existe (mismo <code class="rounded bg-[#e3e8ee] px-1">document</code> o <code class="rounded bg-[#e3e8ee] px-1">phone</code>), se actualiza; si no, se crea.
                    </p>
                    <div class="mt-3 space-y-2 text-sm">
                        <p class="font-medium text-[#33475b]">Método y URL</p>
                        <code class="block rounded bg-[#e3e8ee] p-2 font-mono text-[#33475b]">POST {{ apiClientsUrl }}</code>
                        <p class="font-medium text-[#33475b] mt-3">Autenticación</p>
                        <p class="text-[#425b76]">Header <code class="rounded bg-[#e3e8ee] px-1">Authorization: Bearer TU_API_KEY</code> o <code class="rounded bg-[#e3e8ee] px-1">X-Api-Key: TU_API_KEY</code> (API key de la empresa, en Configuración).</p>
                        <p class="font-medium text-[#33475b] mt-3">Cuerpo (JSON)</p>
                        <pre class="overflow-x-auto rounded bg-[#e3e8ee] p-3 text-xs text-[#33475b]">{{ "{\n  \"name\": \"Juan\",\n  \"lastname\": \"Pérez\",\n  \"email\": \"juan@ejemplo.com\",\n  \"phone\": \"+573001234567\",\n  \"document_type\": \"CC\",\n  \"document\": \"12345678\",\n  \"custom_fields\": { \"ciudad\": \"Bogotá\" }\n}" }}</pre>
                        <p class="text-[#425b76]"><strong>Requeridos:</strong> <code class="rounded bg-[#e3e8ee] px-1">name</code>, <code class="rounded bg-[#e3e8ee] px-1">lastname</code>, <code class="rounded bg-[#e3e8ee] px-1">email</code>. Opcionales: <code class="rounded bg-[#e3e8ee] px-1">phone</code>, <code class="rounded bg-[#e3e8ee] px-1">document_type</code>, <code class="rounded bg-[#e3e8ee] px-1">document</code>, <code class="rounded bg-[#e3e8ee] px-1">custom_fields</code> (objeto).</p>
                        <p class="font-medium text-[#33475b] mt-3">Respuesta (201 creado / 200 actualizado)</p>
                        <pre class="overflow-x-auto rounded bg-[#e3e8ee] p-3 text-xs text-[#33475b]">{{ "{\n  \"success\": true,\n  \"message\": \"Cliente creado\",\n  \"client\": {\n    \"id\": 1,\n    \"name\": \"Juan\",\n    \"lastname\": \"Pérez\",\n    \"email\": \"juan@ejemplo.com\",\n    \"phone\": \"+573001234567\",\n    \"document_type\": \"CC\",\n    \"document\": \"12345678\"\n  }\n}" }}</pre>
                        <p class="font-medium text-[#33475b] mt-3">Ejemplo cURL</p>
                        <pre class="overflow-x-auto rounded bg-[#e3e8ee] p-3 text-xs text-[#33475b]">curl -X POST "{{ apiClientsUrl }}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_API_KEY" \
  -d '{"name":"Juan","lastname":"Pérez","email":"juan@ejemplo.com","phone":"+573001234567"}'</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Importar manualmente -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Importar clientes manualmente</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Si no tienes una fuente externa, importa clientes desde un archivo Excel o CSV usando la plantilla.
                </p>
                <PrimaryButton class="mt-4" @click="openImport">Importar clientes</PrimaryButton>
            </div>
        </div>
    </div>

    <!-- Modal Importar -->
    <div v-show="showImportModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="showImportModal = false">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showImportModal = false" />
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-medium">Importar clientes desde Excel o CSV</h3>
                <p class="mt-1 text-sm text-gray-500">Usa la plantilla para asegurar el formato correcto.</p>

                <div class="mt-6 flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2">
                        <SecondaryButton type="button" @click="downloadTemplate('xlsx')">
                            Descargar plantilla Excel
                        </SecondaryButton>
                        <SecondaryButton type="button" @click="downloadTemplate('csv')">
                            Descargar plantilla CSV
                        </SecondaryButton>
                    </div>

                    <div>
                        <InputLabel value="Archivo (Excel o CSV)" />
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            class="mt-1 block w-full text-sm"
                            @change="onFileSelect"
                        />
                        <p v-if="selectedFile" class="mt-1 text-sm text-gray-600">{{ selectedFile.name }}</p>
                    </div>

                    <div v-if="importLoading" class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span>Subiendo...</span>
                            <span>{{ uploadProgress }}%</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-gray-200">
                            <div
                                class="h-full rounded-full bg-indigo-600 transition-all duration-300"
                                :style="{ width: uploadProgress + '%' }"
                            />
                        </div>
                    </div>

                    <div
                        v-if="importResult"
                        class="rounded-lg p-4"
                        :class="importResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'"
                    >
                        <p class="font-medium">{{ importResult.success ? '✓ Importación completada' : '✗ Error en la importación' }}</p>
                        <p class="mt-1 text-sm">{{ importResult.message }}</p>
                        <ul v-if="importResult.errors?.length" class="mt-2 max-h-32 space-y-1 overflow-y-auto text-sm">
                            <li v-for="(err, i) in importResult.errors" :key="i">{{ err }}</li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showImportModal = false">Cerrar</SecondaryButton>
                    <PrimaryButton
                        :disabled="!selectedFile || importLoading"
                        @click="submitImport"
                    >
                        {{ importLoading ? 'Importando...' : 'Importar' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar endpoint -->
    <div v-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showEditModal = false" />
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-lg font-medium">Editar endpoint</h3>
                <form @submit.prevent="submitEdit" class="mt-4 space-y-4">
                    <div>
                        <InputLabel value="Nombre" />
                        <TextInput v-model="editForm.name" class="mt-1 w-full" required />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel value="URL" />
                        <TextInput v-model="editForm.url" type="url" class="mt-1 w-full" required />
                        <InputError :message="editForm.errors.url" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Hora de ejecución" />
                            <select v-model="editForm.schedule_time" class="mt-1 block w-full rounded-md border-[#e3e8ee]">
                                <option v-for="t in timeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Zona horaria" />
                            <select v-model="editForm.schedule_timezone" class="mt-1 block w-full rounded-md border-[#e3e8ee]">
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Headers (autenticación, etc.)" />
                            <button type="button" class="text-sm text-[var(--color-primary)]" @click="addHeader('edit')">+ Agregar header</button>
                        </div>
                        <div v-for="(h, idx) in editForm.headers" :key="idx" class="mt-2 flex gap-2">
                            <TextInput v-model="h.key" class="flex-1" placeholder="Authorization" />
                            <TextInput v-model="h.value" class="flex-1" placeholder="Bearer xxx" />
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
</template>
