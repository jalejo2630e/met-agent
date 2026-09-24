<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { toast } from 'vue3-toastify';

const props = defineProps({
    files: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => page.props.auth?.canAccessSettings ?? false);

const files = ref([...(props.files || [])]);
const tableFilter = ref('');
const filteredFiles = computed(() => {
    const q = tableFilter.value.trim().toLowerCase();
    if (!q) return files.value;
    return files.value.filter((f) =>
        `${f.name || ''} ${f.original_filename || ''} ${f.uploaded_by || ''}`.toLowerCase().includes(q)
    );
});

// --- Carga ---
const fileInput = ref(null);
const pendingFile = ref(null);
const uploadName = ref('');
const uploadPublic = ref(false);
const uploading = ref(false);
const dragging = ref(false);

function pickFile() {
    fileInput.value?.click();
}

function setPending(file) {
    if (!file) return;
    pendingFile.value = file;
    if (!uploadName.value.trim()) uploadName.value = file.name;
}

function onFileChange(e) {
    setPending(e.target?.files?.[0]);
    if (fileInput.value) fileInput.value.value = '';
}

function onDrop(e) {
    dragging.value = false;
    setPending(e.dataTransfer?.files?.[0]);
}

async function upload() {
    if (!pendingFile.value) {
        pickFile();
        return;
    }
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', pendingFile.value);
        if (uploadName.value.trim()) fd.append('name', uploadName.value.trim());
        fd.append('is_public', uploadPublic.value ? '1' : '0');
        const { data } = await axios.post(route('library.store'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        files.value = [data.file, ...files.value];
        toast.success(data.message || 'Archivo cargado.');
        pendingFile.value = null;
        uploadName.value = '';
        uploadPublic.value = false;
    } catch (e) {
        const errors = e.response?.data?.errors;
        toast.error(errors?.file?.[0] || e.response?.data?.message || 'No se pudo cargar el archivo.');
    } finally {
        uploading.value = false;
    }
}

// --- Edición ---
const busyId = ref(null);

function replaceFile(updated) {
    files.value = files.value.map((f) => (f.id === updated.id ? updated : f));
    if (selected.value?.id === updated.id) selected.value = updated;
}

async function setVisibility(file, isPublic) {
    if (file.is_public === isPublic) return;
    busyId.value = file.id;
    try {
        const { data } = await axios.put(route('library.update', file.id), { is_public: isPublic });
        replaceFile(data.file);
        toast.success(isPublic ? 'Ahora el archivo es público.' : 'Ahora el archivo es privado.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo cambiar la visibilidad.');
    } finally {
        busyId.value = null;
    }
}

const selected = ref(null);
const editName = ref('');

function openDetails(file) {
    selected.value = file;
    editName.value = file.name;
}

function closeDetails() {
    selected.value = null;
}

async function saveName() {
    const name = editName.value.trim();
    if (!selected.value || !name || name === selected.value.name) return;
    busyId.value = selected.value.id;
    try {
        const { data } = await axios.put(route('library.update', selected.value.id), { name });
        replaceFile(data.file);
        toast.success('Nombre actualizado.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo renombrar.');
    } finally {
        busyId.value = null;
    }
}

function canDelete(file) {
    return isAdmin.value || file.user_id === currentUser.value?.id;
}

async function destroy(file) {
    if (!confirm(`¿Eliminar "${file.name}"? El enlace dejará de funcionar.`)) return;
    busyId.value = file.id;
    try {
        await axios.delete(route('library.destroy', file.id));
        files.value = files.value.filter((f) => f.id !== file.id);
        if (selected.value?.id === file.id) closeDetails();
        toast.success('Archivo eliminado.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo eliminar.');
    } finally {
        busyId.value = null;
    }
}

function linkFor(file) {
    return file.is_public ? file.public_url : file.private_url;
}

async function copyLink(file) {
    try {
        await navigator.clipboard.writeText(linkFor(file));
        toast.success(file.is_public ? 'Enlace público copiado.' : 'Enlace privado copiado (requiere iniciar sesión).');
    } catch {
        toast.error('No se pudo copiar el enlace.');
    }
}

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i ? 1 : 0)} ${units[i]}`;
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : '';
}
</script>

<template>
    <Head title="Library" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-[#33475b]">Library</h2>
        </template>

        <div class="py-8">
            <div class="w-full space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Carga -->
                <div class="rounded-lg border border-[#e3e8ee] bg-white p-5">
                    <p class="mb-4 text-sm text-[#425b76]">
                        Carga archivos y decide si su enlace es <strong>público</strong> (cualquiera con el enlace puede abrirlo)
                        o <strong>privado</strong> (solo usuarios con sesión en la plataforma). Puedes cambiarlo en cualquier momento.
                    </p>
                    <div
                        class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-6 text-center text-sm transition"
                        :class="dragging ? 'border-[var(--color-primary)] bg-[#f5f8fa]' : 'border-[#cbd6e2] hover:bg-[#f8fafc]'"
                        @click="pickFile"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <span v-if="pendingFile" class="font-medium text-[#33475b]">
                            {{ pendingFile.name }} · {{ formatSize(pendingFile.size) }}
                        </span>
                        <span v-else class="text-[#425b76]">Arrastra un archivo aquí o haz clic para seleccionarlo (máx. 50 MB)</span>
                    </div>
                    <input ref="fileInput" type="file" class="hidden" @change="onFileChange" />

                    <div class="mt-4 flex flex-wrap items-end gap-4">
                        <div class="min-w-[240px] flex-1">
                            <InputLabel for="upload-name" value="Nombre" />
                            <TextInput id="upload-name" v-model="uploadName" type="text" class="mt-1 block w-full" placeholder="Nombre del archivo" />
                        </div>
                        <div>
                            <InputLabel value="Enlace" />
                            <div class="mt-1 inline-flex overflow-hidden rounded-md border border-[#cbd6e2] text-sm">
                                <button
                                    type="button"
                                    class="px-3 py-2"
                                    :class="!uploadPublic ? 'bg-[#33475b] text-white' : 'bg-white text-[#425b76] hover:bg-[#f5f8fa]'"
                                    @click="uploadPublic = false"
                                >
                                    Privado
                                </button>
                                <button
                                    type="button"
                                    class="border-l border-[#cbd6e2] px-3 py-2"
                                    :class="uploadPublic ? 'bg-green-600 text-white' : 'bg-white text-[#425b76] hover:bg-[#f5f8fa]'"
                                    @click="uploadPublic = true"
                                >
                                    Público
                                </button>
                            </div>
                        </div>
                        <PrimaryButton :disabled="uploading" @click="upload">
                            {{ uploading ? 'Cargando…' : pendingFile ? 'Cargar archivo' : 'Seleccionar archivo' }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Listado -->
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-5">
                        <div class="mb-4">
                            <TextInput v-model="tableFilter" type="text" class="block w-full max-w-sm" placeholder="Filtrar archivos (nombre, quién lo cargó)..." />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-[#e3e8ee]">
                                <thead>
                                    <tr>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">Archivo</th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">Tamaño</th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">Cargado por</th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">Enlace</th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-[#425b76]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e3e8ee] bg-white">
                                    <tr
                                        v-for="(file, idx) in filteredFiles"
                                        :key="file.id"
                                        :class="[idx % 2 === 0 ? 'bg-white' : 'bg-[#f8fafc]', 'hover:bg-[#f5f8fa]/80']"
                                    >
                                        <td class="px-4 py-3 text-sm">
                                            <button type="button" class="text-left font-medium text-[#33475b] hover:underline" @click="openDetails(file)">
                                                {{ file.name }}
                                            </button>
                                            <div class="text-xs text-[#7c98b6]">{{ formatDate(file.created_at) }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-[#425b76]">{{ formatSize(file.size) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-[#425b76]">{{ file.uploaded_by || '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <div class="inline-flex overflow-hidden rounded-full border border-[#cbd6e2] text-xs font-medium">
                                                <button
                                                    type="button"
                                                    class="px-2.5 py-1"
                                                    :disabled="busyId === file.id"
                                                    :class="!file.is_public ? 'bg-[#33475b] text-white' : 'bg-white text-[#425b76] hover:bg-[#f5f8fa]'"
                                                    @click="setVisibility(file, false)"
                                                >
                                                    Privado
                                                </button>
                                                <button
                                                    type="button"
                                                    class="border-l border-[#cbd6e2] px-2.5 py-1"
                                                    :disabled="busyId === file.id"
                                                    :class="file.is_public ? 'bg-green-600 text-white' : 'bg-white text-[#425b76] hover:bg-[#f5f8fa]'"
                                                    @click="setVisibility(file, true)"
                                                >
                                                    Público
                                                </button>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                            <span class="inline-flex gap-3">
                                                <button type="button" class="text-[var(--color-primary)] hover:underline" @click="copyLink(file)">Copiar enlace</button>
                                                <a :href="file.private_url" target="_blank" rel="noopener" class="text-[var(--color-primary)] hover:underline">Abrir</a>
                                                <button type="button" class="text-[var(--color-primary)] hover:underline" @click="openDetails(file)">Editar</button>
                                                <button
                                                    v-if="canDelete(file)"
                                                    type="button"
                                                    class="text-red-600 hover:underline"
                                                    :disabled="busyId === file.id"
                                                    @click="destroy(file)"
                                                >
                                                    Eliminar
                                                </button>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredFiles.length">
                                        <td colspan="5" class="px-4 py-8 text-center text-[#425b76]">
                                            {{ files.length ? 'Sin resultados para el filtro actual.' : 'Aún no hay archivos en la biblioteca.' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle / edición del archivo seleccionado -->
        <Modal :show="!!selected" max-width="lg" @close="closeDetails">
            <div v-if="selected" class="space-y-5 p-6">
                <div>
                    <h3 class="text-lg font-semibold text-[#33475b]">Detalle del archivo</h3>
                    <p class="mt-1 text-xs text-[#7c98b6]">
                        {{ selected.original_filename }} · {{ formatSize(selected.size) }} · {{ selected.mime || 'desconocido' }}
                    </p>
                </div>

                <div>
                    <InputLabel for="edit-name" value="Nombre" />
                    <div class="mt-1 flex gap-2">
                        <TextInput id="edit-name" v-model="editName" type="text" class="block w-full" @keyup.enter="saveName" />
                        <SecondaryButton :disabled="busyId === selected.id || !editName.trim() || editName.trim() === selected.name" @click="saveName">
                            Guardar
                        </SecondaryButton>
                    </div>
                </div>

                <div>
                    <InputLabel value="Visibilidad del enlace" />
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="rounded-lg border p-3 text-left text-sm transition"
                            :class="!selected.is_public ? 'border-[#33475b] ring-1 ring-[#33475b]' : 'border-[#cbd6e2] hover:bg-[#f5f8fa]'"
                            :disabled="busyId === selected.id"
                            @click="setVisibility(selected, false)"
                        >
                            <div class="font-medium text-[#33475b]">Privado</div>
                            <div class="text-xs text-[#425b76]">Solo usuarios con sesión iniciada.</div>
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border p-3 text-left text-sm transition"
                            :class="selected.is_public ? 'border-green-600 ring-1 ring-green-600' : 'border-[#cbd6e2] hover:bg-[#f5f8fa]'"
                            :disabled="busyId === selected.id"
                            @click="setVisibility(selected, true)"
                        >
                            <div class="font-medium text-[#33475b]">Público</div>
                            <div class="text-xs text-[#425b76]">Cualquiera con el enlace.</div>
                        </button>
                    </div>
                </div>

                <div>
                    <InputLabel :value="selected.is_public ? 'Enlace público' : 'Enlace privado'" />
                    <div class="mt-1 flex gap-2">
                        <input
                            :value="linkFor(selected)"
                            readonly
                            class="block w-full rounded-md border-[#cbd6e2] bg-[#f8fafc] text-sm text-[#425b76]"
                            @focus="$event.target.select()"
                        />
                        <SecondaryButton @click="copyLink(selected)">Copiar</SecondaryButton>
                    </div>
                    <p v-if="!selected.is_public" class="mt-1 text-xs text-[#7c98b6]">
                        El enlace público existe pero no funciona mientras el archivo sea privado.
                    </p>
                </div>

                <div class="flex justify-between pt-2">
                    <button
                        v-if="canDelete(selected)"
                        type="button"
                        class="text-sm text-red-600 hover:underline"
                        @click="destroy(selected)"
                    >
                        Eliminar archivo
                    </button>
                    <span v-else />
                    <SecondaryButton @click="closeDetails">Cerrar</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
