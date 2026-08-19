<script setup>
import { computed } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
    collectDataUrl: String,
});

const exampleBody = computed(() => {
    const vars = props.agent?.data_variables || props.agent?.dataVariables || [];
    const body = { phone: '573001234567' };
    if (vars.length) {
        vars.forEach((v) => {
            if (v.name === 'phone') return;
            if (v.type === 'string') body[v.name] = 'valor_ejemplo';
            else if (v.type === 'number') body[v.name] = 123;
            else if (v.type === 'boolean') body[v.name] = true;
            else if (v.type === 'json') body[v.name] = { "clave": "valor" };
            else body[v.name] = 'valor_ejemplo';
        });
    } else {
        body.documento = '123456789';
    }
    return JSON.stringify(body, null, 2);
});

const variableTypes = [
    { value: 'string', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'boolean', label: 'Booleano' },
    { value: 'json', label: 'JSON' },
];

const form = useForm({
    name: '',
    type: 'string',
    required: false,
});

const submit = () => {
    form.post(route('agents.data-variables.store', props.agent), {
        onSuccess: () => form.reset('name', 'type', 'required'),
    });
};

const deleteVariable = (variable) => {
    if (confirm('¿Eliminar esta variable?')) {
        router.delete(route('agents.data-variables.destroy', [props.agent, variable]));
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">Recolección datos WhatsApp</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Datos recolectados en la conversación de WhatsApp, asociados al cliente. Define las variables dinámicas que recibirá el endpoint por POST (con API Key). El cuerpo debe incluir <strong>phone</strong> (sin espacios ni signos) y las variables definidas abajo.
                </p>

                <div class="mt-4 rounded-lg bg-gray-50 p-4">
                    <p class="text-sm font-medium text-gray-700">Endpoint de recolección</p>
                    <code class="mt-1 block break-all text-sm">{{ collectDataUrl }}</code>
                    <p class="mt-2 text-xs text-gray-500">
                        Envía POST con header: Authorization: Bearer {'{api_key}'} o X-Api-Key: {'{api_key}'}. La API key se genera en la pestaña <strong>Configuración</strong>.
                    </p>
                </div>

                <div class="mt-4 rounded-lg border border-[#e3e8ee] bg-[#fafbfc] p-4">
                    <p class="text-sm font-medium text-gray-700">Ejemplo de body (Postman / cliente)</p>
                    <p class="mt-1 text-xs text-gray-500">Content-Type: application/json. <strong>phone</strong> obligatorio, sin espacios ni signos (solo dígitos). El resto son las variables dinámicas definidas abajo (lo recolectado en la conversación).</p>
                    <pre class="mt-3 overflow-x-auto rounded bg-gray-900 p-4 text-left text-sm text-gray-100">{{ exampleBody }}</pre>
                </div>

                <form @submit.prevent="submit" class="mt-6 space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <InputLabel value="Nombre de variable" />
                            <TextInput v-model="form.name" class="mt-1 block w-full" placeholder="ej: documento" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="Tipo" />
                            <select
                                v-model="form.type"
                                class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            >
                                <option v-for="t in variableTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center">
                                <input v-model="form.required" type="checkbox" class="rounded border-[#e3e8ee]" />
                                <span class="ml-2 text-sm text-gray-600">Requerido</span>
                            </label>
                        </div>
                    </div>
                    <PrimaryButton :disabled="form.processing">Agregar variable</PrimaryButton>
                </form>

                <div v-if="agent.data_variables?.length" class="mt-6">
                    <h4 class="text-sm font-medium text-gray-700">Variables definidas</h4>
                    <ul class="mt-2 divide-y divide-gray-200">
                        <li
                            v-for="v in agent.data_variables"
                            :key="v.id"
                            class="flex items-center justify-between py-2"
                        >
                            <span>{{ v.name }} ({{ v.type }}) {{ v.required ? '*' : '' }}</span>
                            <button type="button" class="text-red-600 hover:text-red-800" @click="deleteVariable(v)">
                                Eliminar
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
