<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar contraseña" />

        <h1 class="text-center text-xl font-bold text-[#33475b]">
            Recuperar contraseña
        </h1>
        <p class="mt-2 text-center text-sm text-[#425b76]">
            Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.
        </p>

        <div
            v-if="status"
            class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-center text-sm text-green-800"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Introduce tu email"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <PrimaryButton
                type="submit"
                class="w-full justify-center"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Enviar enlace de recuperación
            </PrimaryButton>

            <div class="text-center">
                <Link
                    :href="route('login')"
                    class="text-sm text-[#425b76] underline hover:text-[var(--color-primary)]"
                >
                    Volver al inicio de sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
