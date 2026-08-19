<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-md border border-[#e3e8ee] bg-white px-5 py-2.5 text-sm font-medium text-[#33475b] transition duration-150 ease-in-out hover:bg-[#f5f8fa] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2 disabled:opacity-50"
    >
        <slot />
    </button>
</template>
