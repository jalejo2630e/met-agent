<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const viewMode = ref('grid');

const props = defineProps({
    agents: Object,
    filters: Object,
});

const statusLabels = {
    active: 'Activo',
    inactive: 'Cancelado',
    draft: 'Borrador',
};

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-red-100 text-red-800',
    draft: 'bg-yellow-100 text-yellow-800',
};

const STATUS_OPTIONS = [
    { value: 'active', label: 'Activo' },
    { value: 'draft', label: 'Borrador' },
    { value: 'inactive', label: 'Cancelado' },
];

const DEFAULT_STATUSES = ['active', 'draft'];

const showFilter = ref(false);
const selectedStatuses = ref([...(props.filters?.statuses?.length ? props.filters.statuses : DEFAULT_STATUSES)]);

// Filtro distinto al de por defecto (activos + borradores) → mostrar indicador.
const filterActive = computed(
    () => [...selectedStatuses.value].sort().join(',') !== [...DEFAULT_STATUSES].sort().join(','),
);

// Mantener los checkboxes en sync con el filtro realmente aplicado (p. ej. al vaciar la selección).
watch(
    () => props.filters?.statuses,
    (val) => {
        if (Array.isArray(val)) selectedStatuses.value = [...val];
    },
);

function reload() {
    router.get(
        route('agents.index'),
        {
            search: props.filters?.search || undefined,
            statuses: selectedStatuses.value.length ? selectedStatuses.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function search(e) {
    router.get(
        route('agents.index'),
        {
            search: e.target.value || undefined,
            statuses: selectedStatuses.value.length ? selectedStatuses.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

function toggleStatus(value) {
    const set = new Set(selectedStatuses.value);
    set.has(value) ? set.delete(value) : set.add(value);
    selectedStatuses.value = [...set];
    reload();
}

</script>

<template>
    <Head title="Empresas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-[#133c75]">
                    Empresas
                </h2>
                <Link :href="route('agents.create')">
                    <PrimaryButton>Crear Empresa</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-5">
                        <div class="mb-4 flex flex-wrap items-center gap-3">
                            <input
                                type="text"
                                placeholder="Buscar por nombre..."
                                :value="filters?.search"
                                @input="search"
                                class="rounded-md border-[#e3e8ee] shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                            />
                            <div class="relative">
                                <button
                                    type="button"
                                    @click="showFilter = !showFilter"
                                    class="inline-flex items-center gap-2 rounded-md border border-[#e3e8ee] bg-white px-3 py-2 text-sm text-[#444444] shadow-sm transition hover:bg-[#f5f8fa]"
                                    title="Filtrar por estado"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 019 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                                    </svg>
                                    Filtros
                                    <span
                                        v-if="filterActive"
                                        class="ml-0.5 inline-block h-2 w-2 rounded-full bg-[var(--color-primary)]"
                                    ></span>
                                </button>

                                <div v-if="showFilter" class="fixed inset-0 z-10" @click="showFilter = false"></div>
                                <div
                                    v-if="showFilter"
                                    class="absolute left-0 z-20 mt-2 w-60 rounded-lg border border-[#e3e8ee] bg-white p-3 shadow-lg"
                                >
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-[#444444]">Estado</p>
                                    <label
                                        v-for="opt in STATUS_OPTIONS"
                                        :key="opt.value"
                                        class="flex cursor-pointer items-center gap-2 py-1.5 text-sm text-[#133c75]"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedStatuses.includes(opt.value)"
                                            @change="toggleStatus(opt.value)"
                                            class="rounded border-[#cbd5e1] text-[var(--color-primary)] focus:ring-[var(--color-primary)]"
                                        />
                                        {{ opt.label }}
                                    </label>
                                    <p class="mt-2 border-t border-[#e3e8ee] pt-2 text-xs text-[#94a3b8]">
                                        Los cancelados están ocultos por defecto.
                                    </p>
                                </div>
                            </div>
                            <div class="ml-auto flex rounded-md border border-[#e3e8ee]">
                                <button
                                    type="button"
                                    :class="[
                                        'rounded-l-md px-3 py-2',
                                        viewMode === 'grid'
                                            ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                            : 'bg-white text-[#444444] hover:bg-[#f5f8fa]'
                                    ]"
                                    title="Vista cuadrícula"
                                    @click="viewMode = 'grid'"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    :class="[
                                        'rounded-r-md border-l border-[#e3e8ee] px-3 py-2',
                                        viewMode === 'list'
                                            ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                            : 'bg-white text-[#444444] hover:bg-[#f5f8fa]'
                                    ]"
                                    title="Vista lista"
                                    @click="viewMode = 'list'"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Vista cuadrícula -->
                        <div v-show="viewMode === 'grid'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div
                                v-for="agent in agents.data"
                                :key="agent.id"
                                class="flex flex-col overflow-hidden rounded-lg border border-[#e3e8ee] bg-white transition hover:border-[color-mix(in_srgb,var(--color-primary)_40%,transparent)] hover:shadow-sm"
                            >
                                <Link :href="route('agents.show', agent)" class="flex flex-1 flex-col p-4">
                                    <h3 class="font-medium text-[#133c75] transition hover:text-[var(--color-primary)]">{{ agent.name }}</h3>
                                    <span
                                        :class="['mt-2 inline-flex w-fit rounded-full px-2 py-1 text-xs font-semibold', statusColors[agent.status] || 'bg-gray-100 text-gray-800']"
                                    >
                                        {{ statusLabels[agent.status] || agent.status }}
                                    </span>
                                    <p class="mt-2 text-sm text-[#444444]">{{ agent.user?.name ?? '-' }}</p>
                                </Link>
                                <div class="flex items-center gap-3 border-t border-[#e3e8ee] bg-[#f5f8fa] px-4 py-2">
                                    <Link
                                        :href="route('agents.edit', agent)"
                                        class="text-sm font-medium text-[var(--color-primary)] transition hover:text-[var(--color-primary-hover)]"
                                    >
                                        Editar
                                    </Link>
                                </div>
                            </div>
                            <div
                                v-if="!agents.data?.length"
                                class="col-span-full rounded-lg border border-dashed border-[#e3e8ee] py-12 text-center text-[#444444]"
                            >
                                No hay empresas. <Link :href="route('agents.create')" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">Crear una</Link>
                            </div>
                        </div>

                        <!-- Vista lista -->
                        <div v-show="viewMode === 'list'" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-[#e3e8ee]">
                                <thead class="bg-[#f5f8fa]">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#444444]">Nombre</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#444444]">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[#444444]">Creado por</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-[#444444]">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e3e8ee] bg-white">
                                    <tr
                                        v-for="(agent, idx) in agents.data"
                                        :key="agent.id"
                                        :class="[
                                            idx % 2 === 0 ? 'bg-white' : 'bg-[#f8fafc]',
                                            'transition hover:bg-[#f5f8fa]'
                                        ]"
                                    >
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <Link :href="route('agents.show', agent)" class="font-medium text-[var(--color-primary)] transition hover:text-[var(--color-primary-hover)]">
                                                {{ agent.name }}
                                            </Link>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold', statusColors[agent.status] || 'bg-gray-100 text-gray-800']"
                                            >
                                                {{ statusLabels[agent.status] || agent.status }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-[#444444]">
                                            {{ agent.user?.name ?? '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                            <Link
                                                :href="route('agents.edit', agent)"
                                                class="font-medium text-[var(--color-primary)] transition hover:text-[var(--color-primary-hover)]"
                                            >
                                                Editar
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="!agents.data?.length">
                                        <td colspan="4" class="px-6 py-8 text-center text-[#444444]">
                                            No hay empresas. <Link :href="route('agents.create')" class="font-medium text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">Crear una</Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="agents.last_page > 1" class="mt-4 flex justify-center gap-2">
                            <template v-for="(link, idx) in agents.links" :key="idx">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    :class="[
                                        'px-3 py-1 rounded text-sm',
                                        link.active ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)] font-medium' : 'text-[#444444] hover:bg-[#f5f8fa]'
                                    ]"
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]' : 'text-[#94a3b8]']"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
