<script setup>
import { computed } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const variables = computed(
    () => props.agent?.extraction_variables || props.agent?.extractionVariables || []
);

const variableTypes = [
    { value: 'string', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'boolean', label: 'Booleano (sí/no)' },
];

const typeLabel = (t) => variableTypes.find((x) => x.value === t)?.label || t;

const form = useForm({
    name: '',
    label: '',
    description: '',
    type: 'string',
});

const submit = () => {
    form.post(route('agents.extraction-variables.store', props.agent), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'label', 'description', 'type'),
    });
};

const deleteVariable = (variable) => {
    if (confirm('¿Eliminar esta variable de recolección?')) {
        router.delete(route('agents.extraction-variables.destroy', [props.agent, variable]), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="border-b border-[#e3e8ee] px-6 py-4">
                <h3 class="text-lg font-semibold text-[#33475b]">Variables de recolección (extraídas de la conversación)</h3>
                <p class="mt-1 text-sm text-[#425b76]">
                    Define qué datos debe <strong>extraer la IA</strong> de la conversación de WhatsApp/SMS (por ejemplo:
                    número de documento, motivo de contacto, ciudad). Tras cada mensaje, la IA analiza el hilo y guarda
                    los valores encontrados. Los verás en la <strong>Bandeja</strong>, dentro de cada conversación.
                </p>
            </div>

            <!-- Alta de variable -->
            <form @submit.prevent="submit" class="grid grid-cols-1 gap-4 border-b border-[#eef2f6] px-6 py-5 md:grid-cols-12">
                <div class="md:col-span-3">
                    <InputLabel value="Nombre (clave) *" />
                    <TextInput
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full font-mono"
                        placeholder="documento"
                        required
                    />
                    <p class="mt-1 text-xs text-gray-400">Solo letras, números y guion bajo.</p>
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div class="md:col-span-3">
                    <InputLabel value="Etiqueta (opcional)" />
                    <TextInput
                        v-model="form.label"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Número de documento"
                    />
                    <InputError :message="form.errors.label" class="mt-1" />
                </div>
                <div class="md:col-span-4">
                    <InputLabel value="¿Qué debe extraer? *" />
                    <TextInput
                        v-model="form.description"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="El número de cédula que menciona el cliente"
                        required
                    />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>
                <div class="md:col-span-2">
                    <InputLabel value="Tipo *" />
                    <select v-model="form.type" class="mt-1 block w-full rounded-md border-[#e3e8ee] text-sm">
                        <option v-for="t in variableTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <InputError :message="form.errors.type" class="mt-1" />
                </div>
                <div class="flex items-end md:col-span-12">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Agregando…' : 'Agregar variable' }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- Listado -->
            <div class="p-6">
                <p v-if="!variables.length" class="text-sm text-gray-400">
                    Aún no hay variables de recolección. Agrega la primera arriba.
                </p>
                <ul v-else class="divide-y divide-[#eef2f6] rounded-md border border-[#eef2f6]">
                    <li v-for="v in variables" :key="v.id" class="flex items-start gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-medium text-[#33475b]">{{ v.name }}</span>
                                <span v-if="v.label" class="text-sm text-[#425b76]">· {{ v.label }}</span>
                                <span class="rounded-full bg-[#f5f8fa] px-2 py-0.5 text-xs text-gray-500">{{ typeLabel(v.type) }}</span>
                            </div>
                            <p class="mt-0.5 text-sm text-[#425b76]">{{ v.description }}</p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 text-sm font-medium text-red-500 hover:text-red-600"
                            @click="deleteVariable(v)"
                        >Eliminar</button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
