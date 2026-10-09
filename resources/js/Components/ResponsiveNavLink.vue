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
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[var(--color-primary)] text-start text-base font-semibold text-[#133c75] bg-[var(--color-primary-light)] focus:outline-none transition duration-150'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#444444] hover:text-[#133c75] hover:bg-[#f5f8fa] focus:outline-none transition duration-150',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
