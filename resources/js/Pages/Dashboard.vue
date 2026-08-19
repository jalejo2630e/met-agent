<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from 'chart.js';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

function hexToRgba(hex, alpha = 1) {
    if (!hex || !hex.startsWith('#')) return `rgba(163, 230, 53, ${alpha})`;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            agents: 0,
            clients: 0,
            whatsapp_contacts: 0,
            call_contacts: 0,
            total_messages: 0,
            total_minutes: 0,
        }),
    },
});

const roleLabels = {
    administrador: 'Administrador',
    editor: 'Editor',
};

const page = usePage();
const primaryColor = computed(() => page.props.settings?.primary_color ?? '#a3e635');

const barChartData = computed(() => ({
    labels: ['Empresas', 'Clientes', 'Contactos WhatsApp', 'Contactos Llamada'],
    datasets: [
        {
            label: 'Cantidad',
            data: [
                props.stats?.agents ?? 0,
                props.stats?.clients ?? 0,
                props.stats?.whatsapp_contacts ?? 0,
                props.stats?.call_contacts ?? 0,
            ],
            backgroundColor: [
                hexToRgba(primaryColor.value, 0.8),
                'rgba(46, 125, 50, 0.7)',
                'rgba(25, 118, 210, 0.7)',
                'rgba(249, 168, 37, 0.7)',
            ],
            borderColor: [
                primaryColor.value,
                '#2e7d32',
                '#1976d2',
                '#f9a825',
            ],
            borderWidth: 1,
        },
    ],
}));

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        title: { display: true, text: 'Resumen general' },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
        },
    },
};

const contactChartData = computed(() => {
    const whatsapp = props.stats?.whatsapp_contacts ?? 0;
    const calls = props.stats?.call_contacts ?? 0;
    const total = whatsapp + calls;
    if (total === 0) {
        return {
            labels: ['Sin contactos'],
            datasets: [{ data: [1], backgroundColor: ['#e5e7eb'] }],
        };
    }
    return {
        labels: ['WhatsApp', 'Llamada'],
        datasets: [
            {
                data: [whatsapp, calls],
        backgroundColor: ['rgba(25, 118, 210, 0.8)', 'rgba(249, 168, 37, 0.8)'],
        borderColor: ['#1976d2', '#f9a825'],
                borderWidth: 2,
            },
        ],
    };
});

const contactChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom' },
        title: { display: true, text: 'Clientes contactados por canal' },
    },
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-[#33475b]">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <!-- Bienvenida -->
                <div class="mb-8 overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-[#33475b]">
                            ¡Bienvenido, {{ $page.props.auth.user.name }}!
                        </h3>
                        <p class="mt-1 text-sm text-[#425b76]">
                            Rol: {{ roleLabels[$page.props.auth.user.role] || $page.props.auth.user.role }}
                        </p>
                        <p class="mt-4 text-[#425b76]">
                            Administrador de Agentes IA - Panel de control
                        </p>
                    </div>
                </div>

                <!-- Tarjetas de estadísticas -->
                <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        :href="route('agents.index')"
                        class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white transition hover:border-[color-mix(in_srgb,var(--color-primary)_30%,transparent)] hover:shadow-md"
                    >
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex shrink-0 rounded-lg bg-[var(--color-primary-light)] p-3">
                                    <svg class="h-6 w-6 text-[var(--color-primary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-[#425b76]">Empresas</p>
                                    <p class="text-2xl font-bold text-[#33475b]">{{ stats?.agents ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </Link>

                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex shrink-0 rounded-lg bg-[#e8f5e9] p-3">
                                    <svg class="h-6 w-6 text-[#2e7d32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-[#425b76]">Clientes</p>
                                    <p class="text-2xl font-bold text-[#33475b]">{{ stats?.clients ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex shrink-0 rounded-lg bg-[#e3f2fd] p-3">
                                    <svg class="h-6 w-6 text-[#1976d2]" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-[#425b76]">Contactos WhatsApp</p>
                                    <p class="text-2xl font-bold text-[#33475b]">{{ stats?.whatsapp_contacts ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex shrink-0 rounded-lg bg-[#fff8e1] p-3">
                                    <svg class="h-6 w-6 text-[#f9a825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-[#425b76]">Contactos por llamada</p>
                                    <p class="text-2xl font-bold text-[#33475b]">{{ stats?.call_contacts ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex shrink-0 rounded-lg bg-[#e3f2fd] p-3">
                                    <svg class="h-6 w-6 text-[#1976d2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-[#425b76]">Total mensajes</p>
                                    <p class="text-2xl font-bold text-[#33475b]">{{ stats?.total_messages ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex shrink-0 rounded-lg bg-[#e8f5e9] p-3">
                                    <svg class="h-6 w-6 text-[#2e7d32]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-[#425b76]">Minutos consumidos</p>
                                    <p class="text-2xl font-bold text-[#33475b]">{{ stats?.total_minutes ?? 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficas -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6">
                        <div class="h-80">
                            <Bar :data="barChartData" :options="barChartOptions" />
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-[#e3e8ee] bg-white p-6">
                        <div class="h-80">
                            <Doughnut :data="contactChartData" :options="contactChartOptions" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
