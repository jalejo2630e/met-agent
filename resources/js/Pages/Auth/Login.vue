<script setup>
import { ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePrimaryColor } from '@/composables/usePrimaryColor';

usePrimaryColor();

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const page = usePage();
const faviconUrl = computed(() => page.props.settings?.favicon_url || '/favicon.png');
const logoUrl = computed(() => page.props.settings?.company_logo_url || '/colsanitas.png');
const companyName = computed(() => page.props.settings?.company_name);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <div class="flex min-h-screen">
        <!-- Columna izquierda: formulario -->
        <div class="flex w-full flex-col justify-between bg-white lg:w-1/2">
            <div class="flex flex-col flex-1 px-6 py-8 sm:px-12 lg:px-16">
                <!-- Logo y formulario centrados -->
                <div class="mx-auto flex w-full max-w-sm flex-1 flex-col items-center pt-4 sm:pt-8">
                    <Link href="/" class="flex justify-center">
                        <img :src="logoUrl" :alt="companyName || 'Logo'" :title="companyName || 'Logo'" class="h-14 w-auto" />
                    </Link>

                    <div class="mt-12 flex w-full flex-1 flex-col sm:mt-16">
                    <h1 class="text-center text-2xl font-bold text-[#33475b] sm:text-3xl">
                        ¡Bienvenido!
                    </h1>
                    <p class="mt-2 text-center text-sm text-[#425b76]">
                        Ingresa tus credenciales para continuar
                    </p>

                    <div v-if="status" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-center text-sm text-green-800">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="mt-8 space-y-5">
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

                        <div>
                            <InputLabel for="password" value="Contraseña" />
                            <div class="relative mt-1 flex items-center">
                                <TextInput
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="block w-full flex-1 pr-10"
                                    v-model="form.password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Introduce tu contraseña"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 flex items-center justify-center text-[#425b76] hover:text-[#33475b] focus:outline-none"
                                    tabindex="-1"
                                    aria-label="Mostrar u ocultar contraseña"
                                >
                                    <svg v-if="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <Checkbox name="remember" v-model:checked="form.remember" />
                                <span class="ms-2 text-sm text-[#425b76]">Recordarme</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm text-[#425b76] underline hover:text-[var(--color-primary)]"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>

                        <PrimaryButton
                            type="submit"
                            class="w-full justify-center"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Iniciar sesión
                        </PrimaryButton>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Footer izquierda -->
            <footer class="border-t border-[#e3e8ee] px-6 py-4 sm:px-12 lg:px-16">
                <div class="flex items-center justify-center gap-2 text-center text-sm text-[#425b76]">
                    <img src="/ainoa.png" alt="ainoa" class="h-6 w-6" />
                    <a href="https://ainoa.app" target="_blank" rel="noopener noreferrer" class="transition hover:text-[var(--color-primary)]">
                        By ainoa
                    </a>
                </div>
            </footer>
        </div>

        <!-- Columna derecha: imagen de fondo + contenido -->
        <div
            class="relative hidden min-h-screen overflow-hidden lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:px-12 lg:py-16 xl:px-20"
        >
            <div
                class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                style="background-image: url('/images/login-bg.jpg')"
                aria-hidden="true"
            />
            <!-- Tinte con el color principal de configuración (50 % de opacidad; la imagen se ve a través) -->
            <div
                class="pointer-events-none absolute inset-0 bg-[var(--color-primary)] opacity-50"
                aria-hidden="true"
            />

            <div class="relative z-10 flex flex-1 flex-col justify-center">
                <h2 class="text-3xl font-bold text-white drop-shadow-sm xl:text-4xl">
                    Administrador de Empresas
                </h2>
                <p class="mt-4 max-w-md text-lg text-white/95 drop-shadow-sm">
                    Gestiona tus empresas, configura integraciones y optimiza la atención a tus clientes desde una sola plataforma.
                </p>
            </div>

            <div class="relative z-10 mt-auto pt-8">
                <p class="text-sm text-white/75 drop-shadow-sm">
                    Plataforma potenciada por inteligencia artificial
                </p>
            </div>
        </div>
    </div>
</template>
