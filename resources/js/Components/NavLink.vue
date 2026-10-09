<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[var(--color-primary)] text-sm font-semibold leading-5 text-[#133c75] focus:outline-none transition duration-150'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-[#444444] hover:text-[#133c75] hover:border-[#e3e8ee] focus:outline-none transition duration-150',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
