<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ConfigClientFields from './Show/ConfigClientFields.vue';
import ConfigClientSource from './Show/ConfigClientSource.vue';
import ConfigClients from './Show/ConfigClients.vue';
import ConfigEndpoints from './Show/ConfigEndpoints.vue';
import ConfigMessages from './Show/ConfigMessages.vue';
import ConfigInbox from './Show/ConfigInbox.vue';
import ConfigCalls from './Show/ConfigCalls.vue';
import ConfigCallbacks from './Show/ConfigCallbacks.vue';
import ConfigCampaigns from './Show/ConfigCampaigns.vue';
import ConfigCollectData from './Show/ConfigCollectData.vue';
import ConfigForm from './Show/ConfigForm.vue';
import ConfigGeneral from './Show/ConfigGeneral.vue';
import ConfigLogs from './Show/ConfigLogs.vue';
import ConfigReports from './Show/ConfigReports.vue';
import { computed, ref, watch, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    agent: Object,
    endpointLogs: Array,
    clients: Object,
    filters: Object,
    collectDataUrl: String,
});

const page = usePage();
const canAccessWebhooksAndTechnical = computed(() => page.props.auth?.canAccessWebhooksAndTechnical === true);

const groups = [
    {
        id: 'clientes',
        label: 'Clientes',
        icon: 'users',
        technical: false,
        items: [
            { id: 'clients', label: 'Lista' },
            { id: 'client-fields', label: 'Campos dinámicos' },
            { id: 'client-source', label: 'Fuente', technical: true },
        ],
    },
    { id: 'general', label: 'Configuración', icon: 'info', technical: false },
    {
        id: 'integracion',
        label: 'Integración',
        icon: 'plug',
        technical: true,
        items: [
            { id: 'endpoints', label: 'Endpoints' },
            { id: 'collect', label: 'Recolección WhatsApp' },
        ],
    },
    {
        id: 'comunicacion',
        label: 'Comunicación',
        icon: 'message',
        technical: true,
        items: [
            { id: 'messages', label: 'Mensajes' },
            { id: 'inbox', label: 'Bandeja' },
            { id: 'calls', label: 'Llamadas' },
            { id: 'callbacks', label: 'Callbacks' },
            { id: 'campaigns', label: 'Campañas' },
        ],
    },
    { id: 'form', label: 'Formulario', icon: 'form', technical: false },
    { id: 'reports', label: 'Reportes', icon: 'reports', technical: false },
    { id: 'logs', label: 'Logs', icon: 'logs', technical: true },
];

const visibleGroups = computed(() =>
    canAccessWebhooksAndTechnical.value ? groups : groups.filter((g) => !g.technical)
);

const flatTabs = computed(() => {
    const out = [];
    visibleGroups.value.forEach((g) => {
        if (g.items) {
            g.items.forEach((item) => {
                if (!item.technical || canAccessWebhooksAndTechnical.value) {
                    out.push({ ...item, groupId: g.id });
                }
            });
        } else {
            out.push({ id: g.id, label: g.label, groupId: g.id });
        }
    });
    return out;
});

const activeGroup = computed(() => {
    const g = visibleGroups.value.find(
        (gr) => gr.id === activeTab.value || gr.items?.some((i) => i.id === activeTab.value)
    );
    return g?.id ?? activeTab.value;
});

const subTabs = computed(() => {
    const g = visibleGroups.value.find(
        (gr) => gr.id === activeTab.value || gr.items?.some((i) => i.id === activeTab.value)
    );
    if (!g?.items) return [];
    return g.items.filter((i) => !i.technical || canAccessWebhooksAndTechnical.value);
});

const STORAGE_KEY = () => `agent-${props.agent?.id ?? 'nav'}-tab`;

const activeTab = ref('clients');

function loadSavedTab() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY());
        if (saved) {
            const validIds = flatTabs.value.map((t) => t.id);
            if (validIds.includes(saved)) {
                activeTab.value = saved;
            }
        }
    } catch {
        // localStorage no disponible
    }
}

function saveTab(tab) {
    try {
        localStorage.setItem(STORAGE_KEY(), tab);
    } catch {
        // localStorage no disponible
    }
}

onMounted(loadSavedTab);

watch(activeTab, (tab) => saveTab(tab));

watch(
    flatTabs,
    (tabs) => {
        const ids = tabs.map((t) => t.id);
        if (ids.length && !ids.includes(activeTab.value)) {
            activeTab.value = ids[0].id;
        }
    },
    { immediate: true }
);

const statusLabels = {
    active: 'Activo',
    inactive: 'Cancelado',
    draft: 'Borrador',
};

const statusColors = {
    active: 'bg-[#e8f5e9] text-[#2e7d32]',
    inactive: 'bg-[#ffebee] text-[#c62828]',
    draft: 'bg-[#fff8e1] text-[#f9a825]',
};

</script>

<template>
    <Head :title="agent.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Link :href="route('agents.index')" class="text-gray-400 hover:text-gray-600">←</Link>
                    <h2 class="text-xl font-semibold leading-tight text-[#33475b]">
                        {{ agent.name }}
                    </h2>
                    <span
                        :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold', statusColors[agent.status] || 'bg-gray-100']"
                    >
                        {{ statusLabels[agent.status] || agent.status }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('agents.edit', agent)">
                        <PrimaryButton>Editar</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <!-- Menú principal (grupos) -->
                <div class="mb-4 border-b border-[#e3e8ee]">
                    <nav class="-mb-px flex flex-wrap gap-2">
                        <button
                            v-for="group in visibleGroups"
                            :key="group.id"
                            :class="[
                                'inline-flex items-center gap-2 rounded-t-lg px-4 py-3 text-sm font-medium transition',
                                activeGroup === group.id
                                    ? 'border-b-2 border-[var(--color-primary)] bg-[var(--color-primary)]/5 text-[var(--color-primary)]'
                                    : 'text-[#425b76] hover:bg-[var(--color-primary-light)]/50 hover:text-[#33475b]'
                            ]"
                            @click="
                                group.items
                                    ? (activeTab = group.items.filter((i) => !i.technical || canAccessWebhooksAndTechnical)[0]?.id ?? group.items[0].id)
                                    : (activeTab = group.id)
                            "
                        >
                            <svg v-if="group.icon === 'users'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <svg v-else-if="group.icon === 'info'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else-if="group.icon === 'plug'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                            </svg>
                            <svg v-else-if="group.icon === 'message'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <svg v-else-if="group.icon === 'form'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <svg v-else-if="group.icon === 'reports'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <svg v-else-if="group.icon === 'logs'" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>{{ group.label }}</span>
                        </button>
                    </nav>
                </div>

                <!-- Sub-tabs (cuando el grupo tiene items) -->
                <div v-if="subTabs.length > 0" class="mb-6 flex flex-wrap gap-2">
                    <button
                        v-for="sub in subTabs"
                        :key="sub.id"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm font-medium transition',
                            activeTab === sub.id
                                ? 'bg-[var(--color-primary)] text-[var(--color-primary-foreground)]'
                                : 'bg-[#f5f8fa] text-[#425b76] hover:bg-[#e3e8ee] hover:text-[#33475b]'
                        ]"
                        @click="activeTab = sub.id"
                    >
                        {{ sub.label }}
                    </button>
                </div>

                <!-- Tab content -->
                <ConfigClients
                    v-if="activeTab === 'clients'"
                    :agent="agent"
                    :clients="clients"
                    :filters="filters"
                    @campaign-created="activeTab = 'campaigns'"
                />
                <ConfigClientFields v-if="activeTab === 'client-fields'" :agent="agent" />
                <ConfigClientSource v-if="activeTab === 'client-source' && canAccessWebhooksAndTechnical" :agent="agent" />
                <ConfigGeneral v-if="activeTab === 'general'" :agent="agent" />

                <ConfigEndpoints v-if="activeTab === 'endpoints' && canAccessWebhooksAndTechnical" :agent="agent" />
                <ConfigLogs v-if="activeTab === 'logs'" :agent="agent" :endpoint-logs="endpointLogs" />
                <ConfigMessages v-if="activeTab === 'messages'" :agent="agent" />
                <ConfigInbox v-if="activeTab === 'inbox'" :agent="agent" />
                <ConfigCalls v-if="activeTab === 'calls'" :agent="agent" />
                <ConfigCallbacks v-if="activeTab === 'callbacks'" :agent="agent" :clients="clients" />
                <ConfigCampaigns v-if="activeTab === 'campaigns'" :agent="agent" />
                <ConfigCollectData v-if="activeTab === 'collect' && canAccessWebhooksAndTechnical" :agent="agent" :collect-data-url="collectDataUrl" />
                <ConfigForm v-if="activeTab === 'form'" :agent="agent" />
                <ConfigReports v-if="activeTab === 'reports'" :agent="agent" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
