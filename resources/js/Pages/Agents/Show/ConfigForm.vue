<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue3-toastify';

const props = defineProps({
    agent: Object,
});

const page = usePage();

/* ----------------------------- Preguntas ------------------------------ */

const questions = ref(
    (props.agent.questions ?? []).map((q) => ({
        id: q.id,
        label: q.label,
        help_text: q.help_text ?? '',
        required: !!q.required,
    }))
);

const savingQuestions = ref(false);

const errors = computed(() => page.props.errors ?? {});

const addQuestion = () => {
    questions.value.push({ id: null, label: '', help_text: '', required: false });
};

const removeQuestion = (index) => {
    questions.value.splice(index, 1);
};

const move = (index, dir) => {
    const target = index + dir;
    if (target < 0 || target >= questions.value.length) return;
    const items = questions.value;
    [items[index], items[target]] = [items[target], items[index]];
};

const saveQuestions = () => {
    savingQuestions.value = true;
    router.put(
        route('agents.questions.update', props.agent),
        { questions: questions.value },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Preguntas guardadas.'),
            onError: () => toast.error('Revisa las preguntas: cada una necesita texto.'),
            onFinish: () => (savingQuestions.value = false),
        }
    );
};

/* ----------------------- Enlaces y respuestas -------------------------- */

const responses = ref([]);
const clientsWithoutLink = ref(0);
const loadingResponses = ref(true);
const generating = ref(false);
const expanded = ref(new Set());

const hasResponses = computed(() => responses.value.length > 0);

const fetchResponses = async () => {
    loadingResponses.value = true;
    try {
        const { data } = await axios.get(route('agents.form.responses', props.agent));
        responses.value = data.responses;
        clientsWithoutLink.value = data.clients_without_link;
    } catch {
        toast.error('No se pudieron cargar los enlaces.');
    } finally {
        loadingResponses.value = false;
    }
};

const generateLinks = async () => {
    generating.value = true;
    try {
        const { data } = await axios.post(route('agents.form.links', props.agent));
        toast.success(data.message);
        await fetchResponses();
    } catch {
        toast.error('No se pudieron generar los enlaces.');
    } finally {
        generating.value = false;
    }
};

const regenerate = async (r) => {
    if (!confirm('Esto invalidará el enlace anterior de este cliente. ¿Continuar?')) return;
    try {
        const { data } = await axios.post(route('agents.form.regenerate', [props.agent, r.id]));
        r.token = data.token;
        r.url = data.url;
        toast.success('Enlace regenerado.');
    } catch {
        toast.error('No se pudo regenerar el enlace.');
    }
};

const copyLink = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Enlace copiado.');
    } catch {
        toast.error('No se pudo copiar. Copia manualmente: ' + url);
    }
};

const toggleAnswers = (id) => {
    if (expanded.value.has(id)) expanded.value.delete(id);
    else expanded.value.add(id);
    // forzar reactividad del Set
    expanded.value = new Set(expanded.value);
};

const clientName = (c) => (c ? `${c.name ?? ''} ${c.lastname ?? ''}`.trim() || '—' : '—');

const formatDate = (iso) => {
    if (!iso) return null;
    return new Date(iso).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
};

onMounted(fetchResponses);
</script>

<template>
    <div class="space-y-6">
        <!-- Editor de preguntas -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Preguntas del formulario</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Define las preguntas (de texto abierto) que responderán los clientes desde su enlace único.
                        </p>
                    </div>
                    <PrimaryButton :disabled="savingQuestions" @click="saveQuestions">
                        {{ savingQuestions ? 'Guardando…' : 'Guardar preguntas' }}
                    </PrimaryButton>
                </div>

                <div v-if="questions.length === 0" class="mt-6 rounded-lg border border-dashed border-[#e3e8ee] py-10 text-center text-sm text-gray-500">
                    Aún no hay preguntas. Agrega la primera.
                </div>

                <div v-else class="mt-6 space-y-4">
                    <div
                        v-for="(q, index) in questions"
                        :key="index"
                        class="rounded-lg border border-[#e3e8ee] bg-[#f9fbfd] p-4"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex flex-col gap-1 pt-6">
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-gray-700 disabled:opacity-30"
                                    :disabled="index === 0"
                                    title="Subir"
                                    @click="move(index, -1)"
                                >▲</button>
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-gray-700 disabled:opacity-30"
                                    :disabled="index === questions.length - 1"
                                    title="Bajar"
                                    @click="move(index, 1)"
                                >▼</button>
                            </div>

                            <div class="flex-1 space-y-3">
                                <div>
                                    <InputLabel :value="`Pregunta ${index + 1}`" />
                                    <TextInput
                                        v-model="q.label"
                                        class="mt-1 block w-full"
                                        placeholder="Ej: ¿Cómo calificarías la atención recibida?"
                                    />
                                    <InputError :message="errors[`questions.${index}.label`]" />
                                </div>
                                <div>
                                    <InputLabel value="Texto de ayuda (opcional)" />
                                    <TextInput
                                        v-model="q.help_text"
                                        class="mt-1 block w-full"
                                        placeholder="Aclaración que verá el cliente debajo de la pregunta"
                                    />
                                </div>
                                <label class="flex items-center">
                                    <input v-model="q.required" type="checkbox" class="rounded border-[#e3e8ee]" />
                                    <span class="ml-2 text-sm text-gray-700">Obligatoria</span>
                                </label>
                            </div>

                            <button
                                type="button"
                                class="pt-6 text-sm text-red-600 hover:text-red-800"
                                @click="removeQuestion(index)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-1 rounded-lg border border-dashed border-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary)] hover:bg-[var(--color-primary)]/5"
                    @click="addQuestion"
                >
                    + Agregar pregunta
                </button>
            </div>
        </div>

        <!-- Enlaces por cliente -->
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Enlaces por cliente</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Cada cliente tiene un enlace único para responder. Las respuestas quedan vinculadas a su ficha.
                        </p>
                    </div>
                    <PrimaryButton :disabled="generating" @click="generateLinks">
                        {{ generating ? 'Generando…' : 'Generar enlaces faltantes' }}
                    </PrimaryButton>
                </div>

                <p v-if="clientsWithoutLink > 0" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    {{ clientsWithoutLink }} cliente(s) aún no tienen enlace. Usa "Generar enlaces faltantes".
                </p>

                <div v-if="loadingResponses" class="mt-6 text-sm text-gray-500">Cargando…</div>

                <div v-else-if="!hasResponses" class="mt-6 rounded-lg border border-dashed border-[#e3e8ee] py-10 text-center text-sm text-gray-500">
                    Todavía no hay enlaces generados.
                </div>

                <div v-else class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#e3e8ee] text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="px-3 py-2">Cliente</th>
                                <th class="px-3 py-2">Teléfono</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Enlace</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#eef2f6]">
                            <template v-for="r in responses" :key="r.id">
                                <tr>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ clientName(r.client) }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ r.client?.phone ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            v-if="r.submitted_at"
                                            class="inline-flex rounded-full bg-[#e8f5e9] px-2 py-1 text-xs font-semibold text-[#2e7d32]"
                                            :title="formatDate(r.submitted_at)"
                                        >
                                            Respondió
                                        </span>
                                        <span v-else class="inline-flex rounded-full bg-[#fff8e1] px-2 py-1 text-xs font-semibold text-[#f9a825]">
                                            Pendiente
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="text-[var(--color-primary)] hover:underline" @click="copyLink(r.url)">
                                                Copiar
                                            </button>
                                            <a :href="r.url" target="_blank" rel="noopener noreferrer" class="text-gray-500 hover:text-gray-800">Abrir</a>
                                            <button type="button" class="text-gray-400 hover:text-red-600" title="Regenerar enlace" @click="regenerate(r)">
                                                Regenerar
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            v-if="r.submitted_at"
                                            type="button"
                                            class="text-gray-500 hover:text-gray-800"
                                            @click="toggleAnswers(r.id)"
                                        >
                                            {{ expanded.has(r.id) ? 'Ocultar' : 'Ver respuestas' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(r.id)">
                                    <td colspan="5" class="bg-[#f9fbfd] px-3 py-3">
                                        <dl class="space-y-2">
                                            <div v-for="(a, i) in r.answers" :key="i">
                                                <dt class="text-xs font-semibold text-gray-500">{{ a.question_label }}</dt>
                                                <dd class="whitespace-pre-line text-gray-800">{{ a.answer || '—' }}</dd>
                                            </div>
                                            <p v-if="!r.answers?.length" class="text-sm text-gray-500">Sin respuestas.</p>
                                        </dl>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
