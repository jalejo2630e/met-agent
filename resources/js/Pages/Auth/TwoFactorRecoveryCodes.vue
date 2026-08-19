<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    recoveryCodes: { type: Array, required: true },
});

const copied = ref(false);

const copyCodes = async () => {
    try {
        await navigator.clipboard.writeText(props.recoveryCodes.join('\n'));
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        // El usuario puede copiar manualmente.
    }
};

const downloadCodes = () => {
    const blob = new Blob([props.recoveryCodes.join('\n')], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'codigos-de-recuperacion.txt';
    a.click();
    URL.revokeObjectURL(url);
};

const goToDashboard = () => {
    router.visit(route('dashboard'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Códigos de recuperación" />

        <h1 class="text-center text-xl font-bold text-[#33475b]">
            Guarda tus códigos de recuperación
        </h1>
        <p class="mt-2 text-center text-sm text-[#425b76]">
            Úsalos para entrar si pierdes acceso a tu app de autenticación.
            Cada código funciona <strong>una sola vez</strong>. Guárdalos en un lugar seguro:
            no volverás a verlos.
        </p>

        <div class="mt-6 grid grid-cols-2 gap-2 rounded-lg bg-[#f5f8fa] p-4">
            <code
                v-for="code in recoveryCodes"
                :key="code"
                class="rounded bg-white px-2 py-1 text-center text-sm font-semibold tracking-wider text-[#33475b]"
            >
                {{ code }}
            </code>
        </div>

        <div class="mt-4 flex justify-center gap-4">
            <button
                type="button"
                class="text-sm text-[var(--color-primary)] underline hover:opacity-80"
                @click="copyCodes"
            >
                {{ copied ? 'Copiado' : 'Copiar códigos' }}
            </button>
            <button
                type="button"
                class="text-sm text-[var(--color-primary)] underline hover:opacity-80"
                @click="downloadCodes"
            >
                Descargar
            </button>
        </div>

        <PrimaryButton
            type="button"
            class="mt-6 w-full justify-center"
            @click="goToDashboard"
        >
            Ya los guardé, continuar
        </PrimaryButton>
    </GuestLayout>
</template>
