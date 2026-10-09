<script setup>
import { computed } from 'vue';
import { Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Title,
} from 'chart.js';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend, Title);

const props = defineProps({
    // 'doughnut' | 'bar' | 'horizontalBar'
    type: { type: String, default: 'doughnut' },
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
    // Serie de detalle opcional que se muestra en el tooltip (mismo largo que values)
    detail: { type: Array, default: () => null },
    // Sufijo/formato del valor: 'count' | 'percent'
    valueFormat: { type: String, default: 'count' },
    height: { type: Number, default: 280 },
});

const DEFAULT_PALETTE = [
    '#2e7d32', '#1976d2', '#f9a825', '#c62828', '#6a1b9a',
    '#00838f', '#ef6c00', '#5e35b1', '#43a047', '#3949ab',
];

const resolvedColors = computed(() =>
    props.labels.map((_, i) => props.colors[i] || DEFAULT_PALETTE[i % DEFAULT_PALETTE.length])
);

const isDoughnut = computed(() => props.type === 'doughnut');
const isHorizontal = computed(() => props.type === 'horizontalBar');

const total = computed(() => props.values.reduce((a, b) => a + (Number(b) || 0), 0));

function formatValue(v) {
    const n = Number(v) || 0;
    if (props.valueFormat === 'percent') return `${n}%`;
    return n.toLocaleString();
}

const chartData = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            data: props.values,
            backgroundColor: resolvedColors.value.map((c) => (isDoughnut.value ? c : c + 'cc')),
            borderColor: resolvedColors.value,
            borderWidth: isDoughnut.value ? 2 : 1,
            borderRadius: isDoughnut.value ? 0 : 6,
            hoverBackgroundColor: resolvedColors.value,
            hoverBorderWidth: isDoughnut.value ? 4 : 2,
            maxBarThickness: 52,
        },
    ],
}));

const commonTooltip = {
    backgroundColor: 'rgba(51, 71, 91, 0.95)',
    titleColor: '#fff',
    bodyColor: '#fff',
    padding: 12,
    cornerRadius: 8,
    displayColors: true,
    boxPadding: 6,
    callbacks: {
        label(ctx) {
            const value = ctx.parsed?.y ?? ctx.parsed?.x ?? ctx.parsed ?? ctx.raw;
            const numeric = typeof value === 'object' ? ctx.raw : value;
            const pct = total.value > 0 ? Math.round((numeric / total.value) * 100) : 0;
            const base = props.valueFormat === 'percent'
                ? formatValue(numeric)
                : `${formatValue(numeric)} (${pct}%)`;
            return ` ${base}`;
        },
        afterLabel(ctx) {
            if (!props.detail || props.detail[ctx.dataIndex] == null) return undefined;
            return props.detail[ctx.dataIndex];
        },
    },
};

const chartOptions = computed(() => {
    const base = {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 900,
            easing: 'easeOutQuart',
        },
        animations: {
            numbers: { duration: 900, easing: 'easeOutQuart' },
        },
        transitions: {
            active: { animation: { duration: 300 } },
        },
        interaction: { intersect: false, mode: 'nearest' },
        plugins: {
            legend: {
                display: isDoughnut.value,
                position: 'bottom',
                labels: { usePointStyle: true, padding: 16, font: { size: 12 } },
            },
            tooltip: commonTooltip,
            title: { display: false },
        },
    };

    if (isDoughnut.value) {
        return {
            ...base,
            cutout: '62%',
            plugins: { ...base.plugins },
        };
    }

    return {
        ...base,
        indexAxis: isHorizontal.value ? 'y' : 'x',
        scales: {
            x: {
                beginAtZero: true,
                grid: { display: !isHorizontal.value ? false : true, color: '#eef2f6' },
                ticks: { color: '#444444', font: { size: 11 } },
            },
            y: {
                beginAtZero: true,
                grid: { display: isHorizontal.value ? false : true, color: '#eef2f6' },
                ticks: { color: '#444444', font: { size: 11 } },
            },
        },
    };
});
</script>

<template>
    <div :style="{ height: height + 'px', position: 'relative' }">
        <Doughnut v-if="isDoughnut" :data="chartData" :options="chartOptions" />
        <Bar v-else :data="chartData" :options="chartOptions" />
    </div>
</template>
