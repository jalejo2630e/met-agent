<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AgentPromptSectionsForm from '@/Components/AgentPromptSectionsForm.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    status: 'draft',
    prompt_configuration: {
        sections: {
            greeting: '',
            behavior: '',
            business_rules: '',
            additional: '',
            tools: [],
        },
    },
});

const statusOptions = [
    { value: 'active', label: 'Activo' },
    { value: 'draft', label: 'Borrador' },
    { value: 'inactive', label: 'Cancelado' },
];

const submit = () => {
    form.post(route('agents.store'));
};
</script>

<template>
    <Head title="Crear Empresa" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('agents.index')" class="text-gray-400 hover:text-gray-600">←</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Crear Nueva Empresa
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
                            <AgentPromptSectionsForm v-model="form.prompt_configuration.sections" />
                            <InputError v-if="form.errors['prompt_configuration.sections']" :message="Array.isArray(form.errors['prompt_configuration.sections']) ? form.errors['prompt_configuration.sections'][0] : form.errors['prompt_configuration.sections']" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <Link
                                :href="route('agents.index')"
                                class="rounded-md border border-[#e3e8ee] px-4 py-2 text-[#33475b] hover:bg-[#f5f8fa]"
                            >
                                Cancelar
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Creando...' : 'Crear Empresa' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
