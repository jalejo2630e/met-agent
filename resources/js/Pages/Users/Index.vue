<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    users: Array,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const roleLabels = {
    administrador: 'Administrador',
    editor: 'Editor',
};

const showModal = ref(false);
const editingUser = ref(null);
const showInviteModal = ref(false);
const tableFilter = ref('');
const filteredUsers = computed(() => {
    const q = tableFilter.value.trim().toLowerCase();
    if (!q) return props.users;
    return (props.users || []).filter((u) =>
        `${u.name || ''} ${u.email || ''} ${u.role || ''}`.toLowerCase().includes(q)
    );
});

const isCreateMode = () => editingUser.value === null;

const form = useForm({
    name: '',
    email: '',
    role: 'editor',
    password: '',
    password_confirmation: '',
});

function openCreate() {
    editingUser.value = null;
    form.reset();
    form.role = 'editor';
    form.password = '';
    form.password_confirmation = '';
    showModal.value = true;
}

function openEdit(user) {
    editingUser.value = user;
    form.reset();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.password = '';
    form.password_confirmation = '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingUser.value = null;
    form.reset();
}

const inviteForm = useForm({
    name: '',
    email: '',
    role: 'editor',
});

function openInvite() {
    inviteForm.reset();
    inviteForm.role = 'editor';
    showInviteModal.value = true;
}

function closeInvite() {
    showInviteModal.value = false;
    inviteForm.reset();
}

function submitInvite() {
    inviteForm.post(route('users.invite'), {
        onSuccess: () => closeInvite(),
    });
}

function resetTwoFactor(user) {
    if (!confirm(`¿Restablecer la verificación en dos pasos de "${user.name}"? Deberá configurarla de nuevo al iniciar sesión.`)) return;
    router.post(route('users.reset-two-factor', user.id), {}, { preserveScroll: true });
}

function submit() {
    if (isCreateMode()) {
        form.post(route('users.store'), {
            onSuccess: () => closeModal(),
        });
    } else {
        const payload = {
            name: form.name,
            email: form.email,
            role: form.role,
        };
        if (form.password) {
            payload.password = form.password;
            payload.password_confirmation = form.password_confirmation;
        }
        form.transform(() => payload).put(route('users.update', editingUser.value.id), {
            onSuccess: () => closeModal(),
        });
    }
}

function canDelete(user) {
    return currentUserId.value !== user.id;
}

function deleteUser(user) {
    if (!canDelete(user)) return;
    if (!confirm(`¿Estás seguro de que deseas eliminar a "${user.name}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('users.destroy', user.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-[#33475b]">
                    Usuarios del sistema
                </h2>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md border border-[var(--color-primary)] bg-white px-4 py-2 text-sm font-medium text-[#33475b] transition hover:bg-[#f5f8fa]"
                        @click="openInvite"
                    >
                        Invitar usuario
                    </button>
                    <PrimaryButton @click="openCreate">
                        Crear usuario
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-5">
                        <p class="mb-4 text-sm text-[#425b76]">
                            Gestiona los usuarios que pueden acceder a la plataforma. Solo administradores pueden ver esta sección.
                        </p>
                        <div class="mb-4">
                            <TextInput
                                v-model="tableFilter"
                                type="text"
                                class="block w-full max-w-sm"
                                placeholder="Filtrar usuarios (nombre, email, rol)..."
                            />
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-[#e3e8ee]">
                                <thead>
                                    <tr>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">
                                            Nombre
                                        </th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">
                                            Email
                                        </th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">
                                            Rol
                                        </th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-[#425b76]">
                                            2FA
                                        </th>
                                        <th class="bg-[#f5f8fa] px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-[#425b76]">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e3e8ee] bg-white">
                                    <tr
                                        v-for="(user, idx) in filteredUsers"
                                        :key="user.id"
                                        :class="[
                                            idx % 2 === 0 ? 'bg-white' : 'bg-[#f8fafc]',
                                            'hover:bg-[#f5f8fa]/80'
                                        ]"
                                    >
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-[#33475b]">
                                            {{ user.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-[#425b76]">
                                            {{ user.email }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="user.role === 'administrador' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'"
                                            >
                                                {{ roleLabels[user.role] ?? user.role }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="user.two_factor_enabled ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                                            >
                                                {{ user.two_factor_enabled ? 'Activo' : 'Pendiente' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                            <span class="inline-flex gap-3">
                                                <button
                                                    type="button"
                                                    class="text-[var(--color-primary)] hover:underline"
                                                    @click="openEdit(user)"
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    v-if="user.two_factor_enabled"
                                                    type="button"
                                                    class="text-amber-600 hover:underline"
                                                    @click="resetTwoFactor(user)"
                                                >
                                                    Restablecer 2FA
                                                </button>
                                                <button
                                                    v-if="canDelete(user)"
                                                    type="button"
                                                    class="text-red-600 hover:underline"
                                                    @click="deleteUser(user)"
                                                >
                                                    Eliminar
                                                </button>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredUsers.length">
                                        <td colspan="5" class="px-4 py-8 text-center text-[#425b76]">Sin resultados para el filtro actual.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal editar usuario -->
        <Teleport to="body">
            <div
                v-show="showModal"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-modal="true"
                role="dialog"
            >
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div
                        class="fixed inset-0 bg-gray-500/75 transition-opacity"
                        aria-hidden="true"
                        @click="closeModal"
                    />
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>
                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle"
                    >
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-semibold text-[#33475b]">
                                {{ isCreateMode() ? 'Crear usuario' : 'Editar usuario' }}
                            </h3>
                            <form @submit.prevent="submit" class="mt-4 space-y-4">
                                <div>
                                    <InputLabel for="user-name" value="Nombre" />
                                    <TextInput
                                        id="user-name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="user-email" value="Email" />
                                    <TextInput
                                        id="user-email"
                                        v-model="form.email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError :message="form.errors.email" />
                                </div>
                                <div>
                                    <InputLabel for="user-role" value="Rol" />
                                    <select
                                        id="user-role"
                                        v-model="form.role"
                                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                    >
                                        <option value="editor">Editor</option>
                                        <option value="administrador">Administrador</option>
                                    </select>
                                    <InputError :message="form.errors.role" />
                                </div>
                                <div>
                                    <InputLabel
                                        for="user-password"
                                        :value="isCreateMode() ? 'Contraseña' : 'Nueva contraseña (opcional)'"
                                    />
                                    <TextInput
                                        id="user-password"
                                        v-model="form.password"
                                        type="password"
                                        class="mt-1 block w-full"
                                        :required="isCreateMode()"
                                        autocomplete="new-password"
                                    />
                                    <InputError :message="form.errors.password" />
                                </div>
                                <div v-if="form.password">
                                    <InputLabel for="user-password-confirmation" value="Confirmar contraseña" />
                                    <TextInput
                                        id="user-password-confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="mt-1 block w-full"
                                        autocomplete="new-password"
                                    />
                                    <InputError :message="form.errors.password_confirmation" />
                                </div>
                                <div class="mt-6 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#425b76] hover:bg-[#f5f8fa]"
                                        @click="closeModal"
                                    >
                                        Cancelar
                                    </button>
                                    <PrimaryButton :disabled="form.processing">
                                        Guardar
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal invitar usuario -->
        <Teleport to="body">
            <div
                v-show="showInviteModal"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-modal="true"
                role="dialog"
            >
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div
                        class="fixed inset-0 bg-gray-500/75 transition-opacity"
                        aria-hidden="true"
                        @click="closeInvite"
                    />
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>
                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle"
                    >
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-semibold text-[#33475b]">
                                Invitar usuario
                            </h3>
                            <p class="mt-1 text-sm text-[#425b76]">
                                Se creará la cuenta y se enviará al correo indicado una contraseña temporal para ingresar. Al iniciar sesión deberá configurar la verificación en dos pasos.
                            </p>
                            <form @submit.prevent="submitInvite" class="mt-4 space-y-4">
                                <div>
                                    <InputLabel for="invite-name" value="Nombre" />
                                    <TextInput
                                        id="invite-name"
                                        v-model="inviteForm.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError :message="inviteForm.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="invite-email" value="Email" />
                                    <TextInput
                                        id="invite-email"
                                        v-model="inviteForm.email"
                                        type="email"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError :message="inviteForm.errors.email" />
                                </div>
                                <div>
                                    <InputLabel for="invite-role" value="Rol" />
                                    <select
                                        id="invite-role"
                                        v-model="inviteForm.role"
                                        class="mt-1 block w-full rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                    >
                                        <option value="editor">Editor</option>
                                        <option value="administrador">Administrador</option>
                                    </select>
                                    <InputError :message="inviteForm.errors.role" />
                                </div>
                                <div class="mt-6 flex justify-end gap-3">
                                    <button
                                        type="button"
                                        class="rounded-md border border-[#e3e8ee] bg-white px-4 py-2 text-sm font-medium text-[#425b76] hover:bg-[#f5f8fa]"
                                        @click="closeInvite"
                                    >
                                        Cancelar
                                    </button>
                                    <PrimaryButton :disabled="inviteForm.processing">
                                        Enviar invitación
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
