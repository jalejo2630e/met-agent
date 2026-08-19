<script setup>
import { ref, nextTick } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const useRecovery = ref(false);
const codeInput = ref(null);
const recoveryInput = ref(null);

const form = useForm({
    code: '',
    recovery_code: '',
});

const submit = () => {
    form.post(route('two-factor.login.store'), {
        onFinish: () => {
            form.reset('code', 'recovery_code');
        },
    });
};

const toggleRecovery = async () => {
    useRecovery.value = !useRecovery.value;
    form.clearErrors();
    form.reset('code', 'recovery_code');
    await nextTick();
    if (useRecovery.value) {
        recoveryInput.value?.focus();
    } else {
        codeInput.value?.focus();
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Verificación en dos pasos" />

        <h1 class="text-center text-xl font-bold text-[#33475b]">
            Verificación en dos pasos
        </h1>

        <p class="mt-2 text-center text-sm text-[#425b76]">
            <template v-if="!useRecovery">
                Ingresa el código de 6 dígitos de tu app de autenticación.
            </template>
            <template v-else>
                Ingresa uno de tus códigos de recuperación.
            </template>
        </p>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div v-if="!useRecovery">
                <InputLabel for="code" value="Código de verificación" />
                <TextInput
                    id="code"
                    ref="codeInput"
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

            <div v-else>
                <InputLabel for="recovery_code" value="Código de recuperación" />
                <TextInput
                    id="recovery_code"
                    ref="recoveryInput"
                    v-model="form.recovery_code"
                    type="text"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full"
                    placeholder="XXXXX-XXXXX"
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <PrimaryButton
                type="submit"
                class="w-full justify-center"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Verificar
            </PrimaryButton>

            <button
                type="button"
                class="w-full text-center text-sm text-[#425b76] underline hover:text-[var(--color-primary)]"
                @click="toggleRecovery"
            >
                <template v-if="!useRecovery">Usar un código de recuperación</template>
                <template v-else>Usar el código de la app</template>
            </button>
        </form>
    </GuestLayout>
</template>
