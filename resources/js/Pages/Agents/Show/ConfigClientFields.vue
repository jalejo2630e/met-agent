<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const fieldForm = useForm({
    field_name: '',
    field_type: 'string',
    required: false,
});

const fieldTypes = [
    { value: 'string', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'boolean', label: 'Booleano' },
    { value: 'date', label: 'Fecha' },
    { value: 'text', label: 'Texto largo' },
];

const submitField = () => {
    fieldForm.post(route('agents.client-fields.store', props.agent), {
        onSuccess: () => fieldForm.reset(),
    });
};

const deleteField = (field) => {
    if (confirm('¿Eliminar este campo?')) {
        router.delete(route('agents.client-fields.destroy', [props.agent, field]));
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Campos dinámicos del cliente</h3>
                <p class="mt-1 text-sm text-gray-500">Agrega campos adicionales para los clientes de esta empresa.</p>
                <form @submit.prevent="submitField" class="mt-4 flex flex-wrap gap-4">
                    <div>
                        <InputLabel value="Nombre del campo" />
                        <TextInput v-model="fieldForm.field_name" class="mt-1" placeholder="ej: teléfono" required />
                        <InputError :message="fieldForm.errors.field_name" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select
                            v-model="fieldForm.field_type"
                            class="mt-1 rounded-md border-[#e3e8ee] shadow-sm"
                        >
                            <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center">
                            <input v-model="fieldForm.required" type="checkbox" class="rounded border-[#e3e8ee]" />
                            <span class="ml-2 text-sm">Requerido</span>
                        </label>
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="fieldForm.processing">Agregar campo</PrimaryButton>
                    </div>
                </form>
                <ul v-if="agent.client_fields?.length" class="mt-4 space-y-1">
                    <li
                        v-for="f in agent.client_fields"
                        :key="f.id"
                        class="flex items-center justify-between rounded bg-gray-50 px-3 py-2"
                    >
                        <span>{{ f.field_name }} ({{ f.field_type }}) {{ f.required ? '*' : '' }}</span>
                        <button type="button" class="text-red-600" @click="deleteField(f)">Eliminar</button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
