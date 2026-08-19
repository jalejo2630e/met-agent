<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    qrCode: { type: String, required: true },
    secret: { type: String, required: true },
});

const copied = ref(false);

const form = useForm({
    code: '',
});

const submit = () => {
    form.post(route('two-factor.setup.store'), {
        onFinish: () => form.reset('code'),
    });
};

const copySecret = async () => {
    try {
        await navigator.clipboard.writeText(props.secret);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        // Ignoramos: el usuario puede copiar manualmente.
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Configura la verificación en dos pasos" />

        <h1 class="text-center text-xl font-bold text-[#33475b]">
            Protege tu cuenta
        </h1>
        <p class="mt-2 text-center text-sm text-[#425b76]">
            La verificación en dos pasos es obligatoria. Escanea el código QR con
            <strong>Google Authenticator</strong> (o una app equivalente) para continuar.
        </p>

        <ol class="mt-6 space-y-2 text-sm text-[#425b76]">
            <li>1. Instala Google Authenticator en tu teléfono.</li>
            <li>2. Escanea este código QR desde la app.</li>
            <li>3. Ingresa el código de 6 dígitos que aparece en la app.</li>
        </ol>

        <div class="mt-4 flex justify-center">
            <img
                :src="qrCode"
                alt="Código QR para la app de autenticación"
                class="h-48 w-48 rounded-lg border border-[#e3e8ee] p-2"
            />
        </div>

        <div class="mt-4">
            <p class="text-center text-xs text-[#425b76]">
                ¿No puedes escanear? Ingresa esta clave manualmente:
            </p>
            <div class="mt-1 flex items-center justify-center gap-2">
                <code class="rounded bg-[#f5f8fa] px-2 py-1 text-sm font-semibold tracking-wider text-[#33475b]">
                    {{ secret }}
                </code>
                <button
                    type="button"
                    class="text-xs text-[var(--color-primary)] underline hover:opacity-80"
                    @click="copySecret"
                >
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </button>
            </div>
        </div>

        <form @submit.prevent="submit" class="mt-6 space-y-5 border-t border-[#e3e8ee] pt-6">
            <div>
                <InputLabel for="code" value="Código de verificación" />
                <TextInput
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    maxlength="6"
                    class="mt-1 block w-full text-center text-lg tracking-[0.5em]"
                    placeholder="000000"
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <PrimaryButton
                type="submit"
                class="w-full justify-center"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Activar verificación en dos pasos
            </PrimaryButton>
        </form>

        <div class="mt-4 text-center">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm text-[#425b76] underline hover:text-[var(--color-primary)]"
            >
                Cerrar sesión
            </Link>
        </div>
    </GuestLayout>
</template>
