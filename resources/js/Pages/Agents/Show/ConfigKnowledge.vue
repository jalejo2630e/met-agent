<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    agent: Object,
});

const loading = ref(false);
const documents = ref([]);
const uploading = ref(false);
const fileInput = ref(null);
const pendingFile = ref(null);
const title = ref('');
const expandedId = ref(null);
const editContent = ref('');
const savingId = ref(null);

async function loadDocuments() {
    loading.value = true;
    try {
        const { data } = await axios.get(route('agents.knowledge-base.index', props.agent));
        documents.value = data.documents ?? [];
    } catch (e) {
        // silencioso
    } finally {
        loading.value = false;
    }
}

function pickFile() {
    fileInput.value?.click();
}

function onFileChange(e) {
    const file = e.target?.files?.[0];
    if (fileInput.value) fileInput.value.value = '';
    if (!file) return;
    pendingFile.value = file;
    if (!title.value.trim()) {
        title.value = file.name.replace(/\.[^.]+$/, '');
    }
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
        if (title.value.trim()) fd.append('title', title.value.trim());
        const { data } = await axios.post(route('agents.knowledge-base.store', props.agent), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        documents.value = [...documents.value, data.document];
        toast.success(data.message || 'Documento cargado.');
        pendingFile.value = null;
        title.value = '';
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo cargar el documento.');
    } finally {
        uploading.value = false;
    }
}

function toggleExpand(doc) {
    if (expandedId.value === doc.id) {
        expandedId.value = null;
        return;
    }
    expandedId.value = doc.id;
    editContent.value = doc.content || '';
}

async function saveContent(doc) {
    savingId.value = doc.id;
    try {
        const { data } = await axios.put(route('agents.knowledge-base.update', [props.agent, doc.id]), {
            content: editContent.value,
        });
        Object.assign(doc, data.document);
        toast.success('Contenido guardado.');
    } catch (e) {
        toast.error(e.response?.data?.message || 'No se pudo guardar.');
    } finally {
        savingId.value = null;
    }
}

async function toggleEnabled(doc) {
    try {
        const { data } = await axios.put(route('agents.knowledge-base.update', [props.agent, doc.id]), {
            enabled: !doc.enabled,
        });
        Object.assign(doc, data.document);
    } catch (e) {
        toast.error('No se pudo cambiar el estado.');
    }
}

async function remove(doc) {
    if (!confirm(`¿Eliminar “${doc.title}” de la base de conocimiento?`)) return;
    try {
        await axios.delete(route('agents.knowledge-base.destroy', [props.agent, doc.id]));
        documents.value = documents.value.filter((d) => d.id !== doc.id);
        if (expandedId.value === doc.id) expandedId.value = null;
        toast.success('Documento eliminado.');
    } catch (e) {
        toast.error('No se pudo eliminar.');
    }
}

function fmtSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

onMounted(loadDocuments);
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="border-b border-[#e3e8ee] px-6 py-4">
                <h3 class="text-lg font-semibold text-[#133c75]">Base de conocimiento</h3>
                <p class="mt-1 text-sm text-[#444444]">
                    Carga documentos (<strong>PDF, TXT o Markdown</strong>). Los PDF se convierten a Markdown
                    automáticamente. El contenido de los documentos <strong>habilitados</strong> se inyecta como contexto
                    en el prompt del agente, para que la IA responda con esa información.
                </p>
            </div>

            <!-- Cargar documento -->
            <div class="border-b border-[#eef2f6] bg-[#f9fbfd] px-6 py-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <InputLabel value="Título (opcional)" />
                        <TextInput v-model="title" type="text" class="mt-1 block w-full" placeholder="Manual de afiliación" />
                    </div>
                    <div class="md:col-span-8">
                        <InputLabel value="Archivo" />
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <input ref="fileInput" type="file" class="hidden" accept=".pdf,.txt,.md,.markdown,application/pdf,text/plain,text/markdown" @change="onFileChange" />
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md border border-[#e3e8ee] bg-white px-3 py-2 text-sm text-[#133c75] hover:bg-[#f5f8fa]"
                                @click="pickFile"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Elegir archivo
                            </button>
                            <span v-if="pendingFile" class="truncate text-sm text-[#444444]">{{ pendingFile.name }} · {{ fmtSize(pendingFile.size) }}</span>
                            <PrimaryButton class="ml-auto" :disabled="uploading || !pendingFile" @click="upload">
                                {{ uploading ? 'Procesando…' : 'Cargar y convertir' }}
                            </PrimaryButton>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Máximo 20 MB. Los PDF escaneados como imagen pueden no extraer texto.</p>
                    </div>
                </div>
            </div>

            <!-- Listado de documentos -->
            <div class="p-6">
                <p v-if="loading" class="text-sm text-gray-400">Cargando…</p>
                <p v-else-if="!documents.length" class="text-sm text-gray-400">Aún no hay documentos en la base de conocimiento.</p>
                <ul v-else class="space-y-3">
                    <li v-for="doc in documents" :key="doc.id" class="overflow-hidden rounded-lg border border-[#e3e8ee]">
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#f0f4f8] text-[#444444]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-[#133c75]">{{ doc.title }}</p>
                                <p class="truncate text-xs text-gray-400">
                                    {{ doc.original_filename }}<span v-if="doc.size"> · {{ fmtSize(doc.size) }}</span>
                                </p>
                            </div>

                            <!-- Toggle habilitado -->
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-2 rounded-full px-3 py-1 text-xs font-medium transition"
                                :class="doc.enabled ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                @click="toggleEnabled(doc)"
                            >
                                <span :class="['h-2 w-2 rounded-full', doc.enabled ? 'bg-emerald-500' : 'bg-gray-400']" />
                                {{ doc.enabled ? 'En el prompt' : 'Deshabilitado' }}
                            </button>

                            <button type="button" class="shrink-0 text-sm text-[#444444] underline hover:text-[#133c75]" @click="toggleExpand(doc)">
                                {{ expandedId === doc.id ? 'Cerrar' : 'Ver / editar' }}
                            </button>
                            <button type="button" class="shrink-0 text-sm font-medium text-red-500 hover:text-red-600" @click="remove(doc)">Eliminar</button>
                        </div>

                        <!-- Editor de Markdown -->
                        <div v-if="expandedId === doc.id" class="border-t border-[#eef2f6] bg-[#f9fbfd] p-4">
                            <textarea
                                v-model="editContent"
                                rows="14"
                                class="w-full rounded-md border-[#e3e8ee] font-mono text-xs leading-relaxed text-[#133c75]"
                                placeholder="Contenido en Markdown…"
                            />
                            <div class="mt-2 flex items-center justify-end gap-2">
                                <button type="button" class="rounded-md border border-[#e3e8ee] px-3 py-2 text-sm text-[#133c75] hover:bg-[#f5f8fa]" @click="expandedId = null">Cancelar</button>
                                <PrimaryButton :disabled="savingId === doc.id" @click="saveContent(doc)">
                                    {{ savingId === doc.id ? 'Guardando…' : 'Guardar contenido' }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
