<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { usePrimaryColor } from '@/composables/usePrimaryColor';

usePrimaryColor();

const props = defineProps({
    token: String,
    agentName: String,
    clientName: String,
    questions: { type: Array, default: () => [] },
    answers: { type: Object, default: () => ({}) },
    submittedAt: { type: String, default: null },
});

const page = usePage();

const logoUrl = computed(() => page.props.settings?.company_logo_url || '/colsanitas.png');
const flashSuccess = computed(() => page.props.flash?.success);

const hasQuestions = computed(() => props.questions.length > 0);

// Permite volver a editar tras un envío exitoso.
const editing = ref(false);
const showThankYou = computed(() => flashSuccess.value && !editing.value);

const form = useForm({
    answers: Object.fromEntries(
        props.questions.map((q) => [q.id, props.answers?.[q.id] ?? ''])
    ),
});

const submit = () => {
    form.post(route('public.form.submit', props.token), {
        preserveScroll: true,
    });
};

const formatDate = (iso) => {
    if (!iso) return null;
    return new Date(iso).toLocaleString('es-CO', { dateStyle: 'long', timeStyle: 'short' });
};
</script>

<template>
    <Head title="Copiloto Amigo" />

    <div class="flex min-h-screen flex-col items-center bg-[#f5f8fa] px-4 py-8">
        <div class="w-full max-w-2xl">
            <div class="mb-6 flex flex-col items-center text-center">
                <img :src="logoUrl" alt="" class="h-14 w-auto" />
                <h1 class="mt-4 text-xl font-semibold text-[#33475b]">Copiloto Amigo</h1>
                <p v-if="clientName" class="mt-1 text-sm text-[#425b76]">Hola, {{ clientName }}</p>
            </div>

            <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white shadow-sm">
                <!-- Pantalla de agradecimiento -->
                <div v-if="showThankYou" class="p-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#e8f5e9]">
                        <svg class="h-6 w-6 text-[#2e7d32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="mt-4 text-lg font-medium text-gray-900">{{ flashSuccess }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Puedes cerrar esta ventana.</p>
                    <button
                        type="button"
                        class="mt-6 text-sm font-medium text-[var(--color-primary)] hover:underline"
                        @click="editing = true"
                    >
                        Editar mis respuestas
                    </button>
                </div>

                <!-- Formulario sin preguntas -->
                <div v-else-if="!hasQuestions" class="p-8 text-center text-sm text-gray-500">
                    Este formulario aún no tiene preguntas disponibles.
                </div>

                <!-- Formulario -->
                <form v-else @submit.prevent="submit" class="p-6 sm:p-8">
                    <p
                        v-if="submittedAt && !flashSuccess"
                        class="mb-6 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700"
                    >
                        Ya enviaste tus respuestas el {{ formatDate(submittedAt) }}. Puedes actualizarlas y volver a enviar.
                    </p>

                    <div class="space-y-6">
                        <div v-for="(q, index) in questions" :key="q.id">
                            <label :for="`q-${q.id}`" class="block text-sm font-medium text-[#33475b]">
                                {{ index + 1 }}. {{ q.label }}
                                <span v-if="q.required" class="text-red-500">*</span>
                            </label>
                            <p v-if="q.help_text" class="mt-0.5 text-xs text-gray-500">{{ q.help_text }}</p>
                            <textarea
                                :id="`q-${q.id}`"
                                v-model="form.answers[q.id]"
                                rows="3"
                                class="mt-2 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                :required="q.required"
                            ></textarea>
                            <p v-if="form.errors[`answers.${q.id}`]" class="mt-1 text-sm text-red-600">
                                {{ form.errors[`answers.${q.id}`] }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-8 w-full rounded-lg bg-[var(--color-primary)] px-4 py-3 text-center font-medium text-[var(--color-primary-foreground)] transition hover:opacity-90 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Enviando…' : 'Enviar respuestas' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
