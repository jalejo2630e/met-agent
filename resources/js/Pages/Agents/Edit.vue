<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
});

const form = useForm({
    name: props.agent.name,
    description: props.agent.description ?? '',
    status: props.agent.status ?? 'draft',
    prompt_configuration: {
        system_prompt: props.agent?.prompt_configuration?.system_prompt ?? '',
    },
});

const statusOptions = [
    { value: 'active', label: 'Activo' },
    { value: 'draft', label: 'Borrador' },
    { value: 'inactive', label: 'Cancelado' },
];

const submit = () => {
    form.put(route('agents.update', props.agent));
};
</script>

<template>
    <Head :title="`Editar: ${agent.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('agents.show', agent)" class="text-gray-400 hover:text-gray-600">←</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Editar Empresa
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <form @submit.prevent="submit" class="p-6">
                        <div class="mb-6">
                            <InputLabel for="name" value="Nombre de la Empresa *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <div class="mb-6">
                            <InputLabel for="status" value="Estado" />
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            >
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                            <InputError :message="form.errors.status" class="mt-1" />
                        </div>

                        <div class="mb-6">
                            <InputLabel for="description" value="Descripción" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                        </div>

                        <div class="mb-6">
                            <InputLabel for="system_prompt" value="Prompt del sistema (agente de texto)" />
                            <p class="mt-0.5 text-xs text-[#444444]">Instrucciones completas del agente en un solo campo: rol, tono, reglas del negocio y límites.</p>
                            <textarea
                                id="system_prompt"
                                v-model="form.prompt_configuration.system_prompt"
                                rows="12"
                                class="mt-1 block w-full rounded-md border-[#e3e8ee] font-mono text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                placeholder="Eres el asistente de [empresa]. Tu rol es... Reglas del negocio: ... Solo respondes sobre [ámbito]..."
                                required
                            />
                            <InputError :message="form.errors['prompt_configuration.system_prompt']" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <Link
                                :href="route('agents.show', agent)"
                                class="rounded-md border border-[#e3e8ee] px-4 py-2 text-[#133c75] hover:bg-[#f5f8fa]"
                            >
                                Cancelar
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
